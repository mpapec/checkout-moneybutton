<h2>Checkout page</h2>
<script src="https://www.moneybutton.com/moneybutton.js"></script>
<div id="swipe_pay">&nbsp;</div>

<script>
var mb_opt = <?php echo json_encode($preq->jsconfig()); ?>;

document.addEventListener("DOMContentLoaded", function(e) {
    "use strict";
    var mbel = document.querySelector("#swipe_pay");
    moneyButton.render(mbel, mb_opt);
});
</script>