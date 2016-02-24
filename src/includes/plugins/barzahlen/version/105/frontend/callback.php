<?php
/**
 * Barzahlen Payment Module (JTL Shop 3)
 *
 * NOTICE OF LICENSE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; version 3 of the License
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see http://www.gnu.org/licenses/
 *
 * @copyright   Copyright (c) 2012 Zerebro Internet GmbH (http://www.barzahlen.de)
 * @author      Alexander Diebler
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

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
