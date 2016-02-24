<?php

$textDE = '<div id="barzahlen_description"><img id="barzahlen_special" src="https://cdn.barzahlen.de/images/barzahlen_special.png" style="float: right; margin-left: 10px; max-width: 180px; max-height: 180px;">Mit Abschluss der Bestellung bekommen Sie einen Zahlschein angezeigt, den Sie sich ausdrucken oder auf Ihr Handy schicken lassen können. Bezahlen Sie den Online-Einkauf mit Hilfe des Zahlscheins an der Kasse einer Barzahlen.de-Partnerfiliale.<br><br><strong>Bezahlen Sie bei:</strong>&nbsp;';
$textEN = '<div id="barzahlen_description"><img id="barzahlen_special" src="https://cdn.barzahlen.de/images/barzahlen_special.png" style="float: right; margin-left: 10px; max-width: 180px; max-height: 180px;">After completing your order you will receive a payment slip from Barzahlen.de that you can easily print out or have it sent via text message to your mobile phone. With the help of that payment slip you can pay your online purchase at one of our retail partners.<br><br><strong>Pay at:</strong>&nbsp;';

for ($i = 1; $i <= 10; $i++) {
    $count = str_pad($i, 2, "0", STR_PAD_LEFT);
    $textDE .= '<img src="https://cdn.barzahlen.de/images/barzahlen_partner_' . $count . '.png" alt="" style="vertical-align: middle; height: 25px;" />';
    $textEN .= '<img src="https://cdn.barzahlen.de/images/barzahlen_partner_' . $count . '.png" alt="" style="vertical-align: middle; height: 25px;" />';
}

$textDE .= '</div><script src="https://cdn.barzahlen.de/js/selection.js"></script>';
$textEN .= '</div><script src="https://cdn.barzahlen.de/js/selection.js"></script>';

$sql = "UPDATE tzahlungsartsprache
        SET cHinweisText = '" . $textDE . "'
        WHERE cISOSprache = 'ger' AND cName = 'Barzahlen'";
$GLOBALS["DB"]->executeQuery($sql, 1);

$sql = "UPDATE tzahlungsartsprache
        SET cHinweisText = '" . $textEN . "'
        WHERE cISOSprache = 'eng' AND cName = 'Barzahlen'";
$GLOBALS["DB"]->executeQuery($sql, 1);
