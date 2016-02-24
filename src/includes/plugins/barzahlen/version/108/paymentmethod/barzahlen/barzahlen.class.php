<?php

include_once (PFAD_ROOT . PFAD_INCLUDES_MODULES . 'PaymentMethod.class.php');
require_once dirname(__FILE__) . '/api/loader.php';

class Barzahlen extends PaymentMethod
{
    const LOGFILE = "jtllogs/barzahlen.log"; //!< Barzahlen log file

    /**
     * Object initiation routine.
     */
    public function init($nAgainCheckout = 0)
    {
        parent::init($nAgainCheckout);
        $this->name = 'Barzahlen';
        $this->caption = 'Barzahlen';
    }

    /**
     * Core payment process after order generation.
     *
     * @global object $oPlugin payment plugin object
     * @global object $smarty smarty template object
     * @param object $order order object
     */
    public function preparePaymentProcess($order)
    {
        global $oPlugin, $smarty;

        // avoid unnecessary api calls and payment slip creation
        if ($this->_gotPaymentHash($order)) {
            header("Location: https://www.barzahlen.de/de/kunden/filialfinder");
            exit();
        }

        $sandbox = $oPlugin->oPluginEinstellungAssoc_arr[$this->cModulId . "_sandbox"];
        $shopId = $oPlugin->oPluginEinstellungAssoc_arr[$this->cModulId . "_shopid"];
        $paymentKey = $oPlugin->oPluginEinstellungAssoc_arr[$this->cModulId . "_paymentkey"];

        $debug = $oPlugin->oPluginEinstellungAssoc_arr[$this->cModulId . "_debug"];
        $hash = $this->generateHash($order);
        $language = $this->_getLanguage();

        $customerEmail = $order->oKunde->cMail;
        $customerStreetNr = $order->oKunde->cStrasse . ' ' . $order->oKunde->cHausnummer;
        $customerZipcode = $order->oKunde->cPLZ;
        $customerCity = $order->oKunde->cOrt;
        $customerCountry = $order->oKunde->cLand;
        $orderId = $order->cBestellNr;
        $amount = number_format($order->fGesamtsumme, 2, '.', '');
        $currency = $order->Waehrung->cISO;

        $api = new Barzahlen_Api($shopId, $paymentKey, $sandbox);
        $api->setDebug($debug, self::LOGFILE);
        $api->setLanguage($language);
        $api->setUserAgent('JTL Shop 3 / Plugin v1.0.8');
        $payment = new Barzahlen_Request_Payment($customerEmail, $customerStreetNr, $customerZipcode, $customerCity, $customerCountry, $amount, $currency, $orderId);
        $payment->setCustomVar($hash);

        try {
            $api->handleRequest($payment);
        } catch (Barzahlen_Exception $e) {
            writeLog(PFAD_ROOT . self::LOGFILE, $e, 1);
        }

        if ($payment->isValid()) {
            $smarty->assign('infotext1', mb_convert_encoding($payment->getInfotext1(), 'iso-8859-15', 'utf-8'));
        } else {
            $this->cancelOrder($order->kBestellung);
            header('Location: ' . gibShopURL() . '/bestellvorgang.php?editZahlungsart=1');
            die();
        }
    }

    /**
     * Tests received data, sends header and updates database.
     *
     * @param object $order order object
     * @param string $paymentHash payment hash
     * @param array $args received data
     */
    public function handleNotification($order, $hash, $args)
    {
        if ($this->verifyNotification($order, $hash, $args)) {

            if ($order->cStatus != BESTELLUNG_STATUS_OFFEN && $order->cStatus != BESTELLUNG_STATUS_IN_BEARBEITUNG) {
                writeLog(PFAD_ROOT . self::LOGFILE, 'IPN: Order already handled', 1);
                return;
            }

            switch ($args['state']) {
                case 'paid':
                    $this->setOrderStatusToPaid($order);
                    $zahlungseingang->kBestellung = $order->kBestellung;
                    $zahlungseingang->cZahlungsanbieter = "Barzahlen";
                    $zahlungseingang->fBetrag = $args['amount'];
                    $zahlungseingang->cISO = $args['currency'];
                    $zahlungseingang->cHinweis = $args['transaction_id'];
                    $zahlungseingang->dZeit = strftime('%Y-%m-%d %H:%M:%S', time());
                    $zahlungseingang->cAbgeholt = 'N';

                    $GLOBALS['DB']->insertRow('tzahlungseingang', $zahlungseingang);

                    $this->sendMail($order->kBestellung, MAILTEMPLATE_BESTELLUNG_BEZAHLT);
                    break;
                case 'expired':
                    $this->cancelOrder($order->kBestellung);
                    break;
                default:
                    header("HTTP/1.1 400 Bad Request");
                    header("Status: 400 Bad Request");
                    return;
            }

            header("HTTP/1.1 200 OK");
            header("Status: 200 OK");
        } else {
            header("HTTP/1.1 400 Bad Request");
            header("Status: 400 Bad Request");
            return;
        }
    }

    /**
     * Unused function. Redirects to verifyNotification().
     *
     * @param object $order order object
     * @param string $paymentHash payment hash
     * @param array $args received data
     * @return boolean
     */
    public function finalizeOrder($order, $paymentHash, $args)
    {
        return $this->verifyNotification($order, $paymentHash, $args);
    }

    /**
     * Verifies the received data.
     *
     * @param object $order order object
     * @param string $paymentHash payment hash
     * @param array $args received data
     * @return boolean
     */
    public function verifyNotification($order, $paymentHash, $args)
    {
        $sql = "SELECT cModulId FROM tzahlungsart WHERE cName = 'Barzahlen'";
        $module = $GLOBALS["DB"]->executeQuery($sql, 1);
        $sql = "SELECT cWert FROM tplugineinstellungen WHERE cName = '" . $module->cModulId . '_shopid' . "'";
        $shopId = $GLOBALS["DB"]->executeQuery($sql, 1);
        $sql = "SELECT cWert FROM tplugineinstellungen WHERE cName = '" . $module->cModulId . '_notificationkey' . "'";
        $notificationKey = $GLOBALS["DB"]->executeQuery($sql, 1);

        $notification = new Barzahlen_Notification($shopId->cWert, $notificationKey->cWert, $args);
        try {
            $notification->validate();
        } catch (Barzahlen_Exception $e) {
            writeLog(PFAD_ROOT . self::LOGFILE, $e, 1);
        }

        return $notification->isValid();
    }

    /**
     * @return boolean
     */
    public function canPayAgain()
    {
        return false;
    }

    /**
     * Checks if there's already a payment hash and therefore a valid barcode.
     *
     * @param object $order order object
     * @return boolean
     */
    protected function _gotPaymentHash($order)
    {
        $hash = $GLOBALS['DB']->executeQuery("SELECT cId FROM tzahlungsid WHERE kBestellung = '" . $order->kBestellung . "'", 1);
        return $hash->cId != "";
    }

    /**
     * Gets session language. If not available standard language is returned.
     * In case there's no supported language, German is choosen.
     *
     * @return string
     */
    protected function _getLanguage()
    {
        $cISOSprache_arr = array("DE", "EN");
        $cISOSprache = "";

        if (strlen($_SESSION['cISOSprache']) > 0) {
            $cISOSprache = convertISO2ISO639($_SESSION['cISOSprache']);
        } else {
            $oSprache = $GLOBALS['DB']->executeQuery("SELECT kSprache, cISO FROM tsprache WHERE cShopStandard = 'Y'", 1);
            if ($oSprache->kSprache > 0)
                $cISOSprache = convertISO2ISO639($oSprache->cISO);
        }

        if (!in_array(strtoupper($cISOSprache), $cISOSprache_arr)) {
            $cISOSprache = "DE";
        }

        return $cISOSprache;
    }
}
