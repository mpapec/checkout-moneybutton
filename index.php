<?php

/*
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
*/

require_once __DIR__ ."/vendor/autoload.php";

require "config.php";
require "lib/payment.class.php";
$cfg = cfg();

//
function log_it ($o) {
     return file_put_contents(
        "log.txt",
        date("Y-m-d H:i:s") ." - ". json_encode($o) ."\n",
        FILE_APPEND|LOCK_EX
    );
}

//
function register_payment () {

    global $cfg;

    $presp = new payment_response();
    $presp->mb_secret( $cfg["mb_secret"] );
    $presp->jwt_secret( $cfg["jwt_secret"] );
    //
    if (!$presp->ok()) {
        // $err = $presp->err();
        log_it($presp->dump());
        return;
    }

    // all checks are ok!
    $p = $presp->payment();
    $order = $presp->orderData();

    // print_r($p);
    log_it([
        "msg" => "payment received!",
        "orderId" => $order["id"],
        "senderPaymail" => $p["senderPaymail"],
        "senderEmail" => $p["user"]["email"],
        // "senderGravatarKey" => $p["user"]["gravatarKey"],
        // "signaturePubkey" => $p["signaturePubkey"],
    ]);
}

/*

*/
$Q = $_GET + $_POST + ["a" => ""];

if ($Q["a"] === "mb_whook") {
    register_payment();
}
else {
    $preq = new payment_request([
        "clientIdentifier" => $cfg["mb_id"],
        // "label" => "Swipe to pay",
        // "successMessage" => "Thank you!",
    ]);
    $preq->jwt_secret( $cfg["jwt_secret"] );
    $preq->pay("USD", [
        [ "to" => "mp@moneybutton.com", "amount" => 0.1 ],
        // [ "to" => "business_partner@handcash.io", "amount" => 0.7 ],
        // [ "to" => "future_tax_office@simply.cash", "amount" => 0.1 ],
    ]);
    /* arbitrary order related data */
    $preq->orderData([
        "id" => 12345,
        // foobar => ..,
    ]);
    include "tpl/checkout.php";
}
