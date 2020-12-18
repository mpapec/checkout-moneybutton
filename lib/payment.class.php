<?php

use Ahc\Jwt\JWT;

class utilx {

    // no instances of the class
    private function __construct() {}

    public static function jwt ($input, $sec, $maxage=3600*48, $algo="HS512") {

        if (!$input) { return; }
        if (!$sec) { die("Can't do without a secret"); }

        $ret = null;
        try {
            $jwt = new JWT($sec, $algo, $maxage);
            $ret = is_scalar($input)
                // blame it on JWT inflexibility
                ? json_decode( json_encode($jwt->decode($input)), 1 )
                : $jwt->encode($input)
            ;
        }
        catch (Exception $e) {
            error_log( print_r([$input, $e->getMessage()] ,1) );
            // print_r( print_r([$input, $e->getMessage()] ,1) );
        }
        return $ret;
    }
}

class payment_request {
    protected $opt = [];
    protected $orderData = [];
    protected $jwt_secret = "";

    public function __construct($arg=[]) {
        $this->opt = $arg + [
            "type" => "buy",
            "label" => "Swipe to pay",
            "successMessage" => "Thank you!",
        ];
    }
    public function jwt_secret($secret) {
        $this->jwt_secret = $secret;
    }
    public function orderData($arg) {
        $this->orderData = $arg;
    }
    //
    public function pay($currency, $outputs) {
        foreach ($outputs as &$v) $v["currency"] = $currency;
        $this->opt["outputs"] = $outputs;
    }
    //
    public function jsconfig() {
        $ret = $this->opt;
        if (!$ret["clientIdentifier"]) return ["err" => "clientIdentifier/mb_id not set"];
        if (!$ret["outputs"]) return ["err" => "Payment recipient(s) not set"];
        if (!$this->jwt_secret) return ["err" => "jwt_secret not set"];
        if (!$this->orderData) return ["err" => "orderData not set"];

        // foreach (["orderData"] as $v) unset($ret[$v]);
        $this->orderData["_pvt"]["outputs"] = $ret["outputs"];
        $ret += [
            // "buttonId" => "",
            // "opReturn" => "",
            // "clientIdentifier" => $this->opt["mb_id"],
            "buttonData" => utilx::jwt( $this->orderData, $this->jwt_secret ),
        ];

        //
        return $ret;
    }
}

class payment_response {
    protected $opt = [];
    protected $jwt_secret = "";
    protected $mb_secret = "";
    protected $orderData;
    protected $err;

    public function __construct($arg=[]) {
        $arg += [
            // response =>
            "statusHandler" => function ($status) {
                if ($status !== "RECEIVED") exit;
                return true;
            },
        ];
        if (!isset($arg["response"])) {
            $stdin = file_get_contents("php:/"."/input");
            $arg["response"] = $stdin ? json_decode($stdin,1) : [];
        }
        //
        $this->opt = $arg;
    }

    //
    public function orderData($data=null) {

        if (!$this->orderData) {
            $this->orderData = utilx::jwt( $data ?: $this->opt["response"]["payment"]["buttonData"], $this->jwt_secret );
        }
        return $this->orderData;
    }
    public function mb_secret($secret) {
        $this->mb_secret = $secret;        
    }
    public function jwt_secret($secret) {
        $this->jwt_secret = $secret;
    }
    public function paymentOk() {
        $r = $this->opt["response"];
        $tmp = $this->orderData();
        $o = $tmp["_pvt"]["outputs"];
        
        $po = $r["payment"]["paymentOutputs"] ?: [];
        // get rid of opreturn output
        if ($po and !$po[0]["to"]) array_shift($po);
        foreach ($o as $i => $v) {
            $v2 = $po[$i];
            /*
                [to] => mp@metanet.id
                [amount] => 0.01
                [currency] => USD
            */
            $v["amount"] += 0;
            $v2["amount"] += 0;
            if ($v["to"] !== $v2["to"]) { $this->err = sprintf("Payment recipient error, '%s' but '%s' found", $v["to"], $v2["to"]); return false; }
            if ($v["amount"] !== $v2["amount"]) { $this->err = sprintf("Payment amount error, '%s' but '%s' found", $v["amount"], $v2["amount"]); return false; }
            if ($v["currency"] !== $v2["currency"]) { $this->err = sprintf("Payment currency error, '%s' but '%s' found", $v["currency"], $v2["currency"]); return false; }
        }
        return true;
    }
    public function ok() {
        $r = $this->opt["response"];
        $statusHandler = $this->opt["statusHandler"];

        $this->err = "";
        // checks
        if     (!$this->mb_secret) { $this->err = "Configuration error, no mb_secret set"; }
        elseif (!$this->jwt_secret) { $this->err = "Configuration error, no jwt_secret set"; }
        elseif ($r["secret"] !== $this->mb_secret) { $this->err = "Service error, mb_secret doesn't match"; }
        elseif (!$statusHandler( $r["payment"]["status"] )) { $this->err = "Service warning, statusHandler ignoring message"; }
        elseif (!$this->orderData()) { $this->err = "Service error, invalid orderData"; }
        elseif (!$this->paymentOk()) { /* $this->err = "Service error, invalid orderData"; */ }
        // else { $this->err = ""; }
        
        //
        return !$this->err;
    }
    public function err() { return $this->err; }
    public function payment() {

        return $this->opt["response"]["payment"];
    }
    public function dump() {
        return [
            "response" => $this->opt["response"],
            "orderData" => $this->orderData(),
            "err" => $this->err(),
        ];
    }

}

