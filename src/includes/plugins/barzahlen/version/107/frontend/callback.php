<?php

if (isset($_GET['custom_var_0'])) {
    $paymentHash = htmlentities(filterXSS($_GET['custom_var_0']));

    $sql = "SELECT ZID.kBestellung, ZA.cModulId, ZA.cName FROM tzahlungsid ZID LEFT JOIN tzahlungsart ZA ON ZA.kZahlungsart = ZID.kZahlungsart WHERE ZID.cId = '$paymentHash' ";
    $paymentId = $GLOBALS["DB"]->executeQuery($sql, 1);

    if ($paymentId === false) {
        die(); // Payment Hash does not exist
    }

    // if Barzahlen was choosen, try to handle notification
    if ($paymentId->cName == 'Barzahlen') {

        $moduleId = $paymentId->cModulId;
        $order = new Bestellung($paymentId->kBestellung);
        $order->fuelleBestellung(0);

        include_once(PFAD_ROOT . PFAD_INCLUDES_MODULES . 'PaymentMethod.class.php');
        $paymentMethod = PaymentMethod::create($moduleId);
        if (isset($paymentMethod)) {
            $paymentMethod->handleNotification($order, $paymentHash, $_REQUEST);
        }
    }
}
