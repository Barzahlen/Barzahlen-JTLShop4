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

$textDE = '<div><img src="https://cdn.barzahlen.de/images/barzahlen_special.png" style="float: right; margin-left: 10px; max-width: 180px; max-height: 180px;">Mit Abschluss der Bestellung bekommen Sie einen Zahlschein angezeigt, den Sie sich ausdrucken oder auf Ihr Handy schicken lassen können. Bezahlen Sie den Online-Einkauf mit Hilfe des Zahlscheins an der Kasse einer Barzahlen-Partnerfiliale.</div><br><br>';
$textEN = '<div><img src="https://cdn.barzahlen.de/images/barzahlen_special.png" style="float: right; margin-left: 10px; max-width: 180px; max-height: 180px;">After completing your order you get a payment slip from Barzahlen that you can easily print out or have it sent via SMS to your mobile phone. With the help of that payment slip you can pay your online purchase at one of our retail partners (e.g. supermarket).</div><br><br>';

$sql = "SELECT cModulId FROM tzahlungsart WHERE cName = 'Barzahlen'";
$module = $GLOBALS["DB"]->executeQuery($sql, 1);
$sql = "SELECT cWert FROM tplugineinstellungen WHERE cName = '" . $module->cModulId . '_sandbox' . "'";
$sandbox = $GLOBALS["DB"]->executeQuery($sql, 1);
$sql = "SELECT cWert FROM tplugineinstellungen WHERE cName = '" . $module->cModulId . '_max' . "'";
$maxAmount = $GLOBALS["DB"]->executeQuery($sql, 1);

if ($sandbox->cWert == 1) {
    $textDE .= 'Der <strong>Sandbox Modus</strong> ist aktiv. Allen getätigten Zahlungen wird ein Test-Zahlschein zugewiesen. Dieser kann nicht von unseren Einzelhandelspartnern verarbeitet werden.<br><br>';
    $textEN .= 'The <strong>Sandbox Mode</strong> is active. All placed orders receive a test payment slip. Test payment slips cannot be handled by our retail partners.<br><br>';
}

$textDE .= '<strong>Bezahlen Sie bei:</strong>&nbsp;';
$textEN .= '<strong>Pay at:</strong>&nbsp;';

for ($i = 1; $i <= 10; $i++) {
    $count = str_pad($i, 2, "0", STR_PAD_LEFT);
    $textDE .= '<img src="https://cdn.barzahlen.de/images/barzahlen_partner_' . $count . '.png" alt="" style="vertical-align: middle; height: 25px;" />';
    $textEN .= '<img src="https://cdn.barzahlen.de/images/barzahlen_partner_' . $count . '.png" alt="" style="vertical-align: middle; height: 25px;" />';
}

$sql = "UPDATE tzahlungsartsprache
        SET cHinweisText = '" . $textDE . "'
        WHERE cISOSprache = 'ger' AND cName = 'Barzahlen'";
$GLOBALS["DB"]->executeQuery($sql, 1);

$sql = "UPDATE tzahlungsartsprache
        SET cHinweisText = '" . $textEN . "'
        WHERE cISOSprache = 'eng' AND cName = 'Barzahlen'";
$GLOBALS["DB"]->executeQuery($sql, 1);

if ($maxAmount->cWert <= 0 || $maxAmount->cWert >= 1000 || !is_numeric($maxAmount->cWert)) {

    $sql = "UPDATE tplugineinstellungen
          SET cWert = '999'
          WHERE cName = '" . $module->cModulId . '_max' . "'";
    $GLOBALS["DB"]->executeQuery($sql, 1);
}
