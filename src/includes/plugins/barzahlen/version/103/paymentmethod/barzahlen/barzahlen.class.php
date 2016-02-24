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

include_once (PFAD_ROOT.PFAD_INCLUDES_MODULES.'PaymentMethod.class.php');
require_once dirname(__FILE__) . '/api/loader.php';

class Barzahlen extends PaymentMethod {

  const LOGFILE = "jtllogs/barzahlen.log"; //!< Barzahlen log file

  /**
   * Object initiation routine.
   */
  public function init() {

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
  public function preparePaymentProcess($order) {

    global $oPlugin, $smarty;

    // avoid unnecessary api calls and payment slip creation
    if($this->_gotPaymentHash($order)) {
      header("Location: http://www.barzahlen.de/filialfinder");
      exit();
    }

    $sandbox = $oPlugin->oPluginEinstellungAssoc_arr[$this->cModulId . "_sandbox"];
    $shopId = $oPlugin->oPluginEinstellungAssoc_arr[$this->cModulId . "_shopid"];
    $paymentKey = $oPlugin->oPluginEinstellungAssoc_arr[$this->cModulId . "_paymentkey"];

    $debug = $oPlugin->oPluginEinstellungAssoc_arr[$this->cModulId . "_debug"];
    $hash = $this->generateHash($order);
    $language = $this->_getLanguage();

    $customerEmail = $order->oKunde->cMail;
    $customerStreetNr = $order->oKunde->cStrasse .' '. $order->oKunde->cHausnummer;
    $customerZipcode = $order->oKunde->cPLZ;
    $customerCity = $order->oKunde->cOrt;
    $customerCountry = $order->oKunde->cLand;
    $orderId = $order->cBestellNr;
    $amount = number_format($order->fGesamtsumme, 2, '.', '');
    $currency = $order->Waehrung->cISO;

    $api = new Barzahlen_Api($shopId, $paymentKey, $sandbox);
    $api->setDebug($debug, self::LOGFILE);
    $api->setLanguage($language);
    $payment = new Barzahlen_Request_Payment($customerEmail, $customerStreetNr, $customerZipcode, $customerCity, $customerCountry, $amount, $currency, $orderId);
    $payment->setCustomVar($hash);

    try {
      $api->handleRequest($payment);
    }
    catch (Barzahlen_Exception $e) {
      writeLog(PFAD_ROOT . self::LOGFILE, $e, 1);
    }

    if($payment->isValid()) {
      $smarty->assign('infotext1', mb_convert_encoding($payment->getInfotext1(), 'iso-8859-15', 'utf-8'));
      $this->_updatePaymentState($order->kBestellung, 'pending');
    }
    else {
      $this->cancelOrder($order->kBestellung);
      $this->_updatePaymentState($order->kBestellung, 'error');
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
  public function handleNotification($order, $hash, $args) {

    if($this->verifyNotification($order, $hash, $args)) {
      header("HTTP/1.1 200 OK");
      header("Status: 200 OK");

      if(!$this->_checkOrderDetails($order, $args)) {
        return;
      }

      switch($args['state']) {
        case 'paid':
          $this->setOrderStatusToPaid($order);
          $zahlungseingang->kBestellung = $order->kBestellung;
          $zahlungseingang->cZahlungsanbieter = "Barzahlen";
          $zahlungseingang->fBetrag = $args['amount'];
          $zahlungseingang->cISO = $args['currency'];
          $zahlungseingang->cHinweis = $args['transaction_id'];
          $zahlungseingang->dZeit = strftime('%Y-%m-%d %H:%M:%S', time());

          $GLOBALS['DB']->insertRow('tzahlungseingang', $zahlungseingang);
          $this->_updatePaymentState($order->kBestellung, $args['state']);
          break;
        case 'expired':
          $this->cancelOrder($order->kBestellung);
          $this->_updatePaymentState($order->kBestellung, $args['state']);
          break;
        default:
          break;
      }
    }
    else {
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
  public function finalizeOrder($order, $paymentHash, $args) {

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
  public function verifyNotification($order, $paymentHash, $args) {

    $sql = "SELECT cModulId FROM tzahlungsart WHERE cName = 'Barzahlen'";
    $module = $GLOBALS["DB"]->executeQuery($sql, 1);
    $sql = "SELECT cWert FROM tplugineinstellungen WHERE cName = '".$module->cModulId.'_shopid'."'";
    $shopId = $GLOBALS["DB"]->executeQuery($sql, 1);
    $sql = "SELECT cWert FROM tplugineinstellungen WHERE cName = '".$module->cModulId.'_notificationkey'."'";
    $notificationKey = $GLOBALS["DB"]->executeQuery($sql, 1);

    $notification = new Barzahlen_Notification($shopId->cWert, $notificationKey->cWert, $args);
    try {
      $notification->validate();
    }
    catch (Barzahlen_Exception $e) {
      writeLog(PFAD_ROOT . self::LOGFILE, $e, 1);
    }

    return $notification->isValid();
  }

  /**
   * @return boolean
   */
  public function canPayAgain() {
    return false;
  }

  /**
   * Checks if there's already a payment hash and therefore a valid barcode.
   *
   * @param object $order order object
   * @return boolean
   */
  protected function _gotPaymentHash($order) {
    $hash = $GLOBALS['DB']->executeQuery("SELECT cId FROM tzahlungsid WHERE kBestellung = '".$order->kBestellung."'", 1);
    return $hash->cId != "";
  }

  /**
   * Gets session language. If not available standard language is returned.
   * In case there's no supported language, German is choosen.
   *
   * @return string
   */
  protected function _getLanguage() {

    $cISOSprache_arr = array("DE", "EN");
    $cISOSprache = "";

    if(strlen($_SESSION['cISOSprache']) > 0) {
      $cISOSprache = convertISO2ISO639($_SESSION['cISOSprache']);
    }
    else
    {
      $oSprache = $GLOBALS['DB']->executeQuery("SELECT kSprache, cISO FROM tsprache WHERE cShopStandard = 'Y'", 1);
      if($oSprache->kSprache > 0)
        $cISOSprache = convertISO2ISO639($oSprache->cISO);
    }

    if(!in_array(strtoupper($cISOSprache), $cISOSprache_arr)) {
      $cISOSprache = "DE";
    }

    return $cISOSprache;
  }

  /**
   * Checks if order details fit with received data.
   *
   * @param object $order order object
   * @param array $args array with received data
   * @return boolean
   */
  protected function _checkOrderDetails($order, $args) {

    if(number_format($order->fGesamtsumme, 2, '.', '') != $args['amount']) {
      writeLog(PFAD_ROOT . self::LOGFILE, 'IPN: Amount not valid - ' . serialize($args), 1);
      return false;
    }

    if($order->cStatus != BESTELLUNG_STATUS_OFFEN && $order->cStatus != BESTELLUNG_STATUS_IN_BEARBEITUNG) {
      writeLog(PFAD_ROOT . self::LOGFILE, 'IPN: Order already handled', 1);
      return false;
    }

    $currency = $GLOBALS['DB']->executeQuery("SELECT cISO FROM twaehrung WHERE kWaehrung = '".$order->kWaehrung."'", 1);

    if($currency->cISO != $args['currency']) {
      writeLog(PFAD_ROOT . self::LOGFILE, 'IPN: Currency not valid - ' . serialize($args), 1);
      return false;
    }

    return true;
  }

  /**
   * Updates transaction state, so it's shown in WaWi.
   *
   * @param integer $kBestellung order id
   * @param string $state new transaction state
   */
  protected function _updatePaymentState($kBestellung, $state) {

    $sql = "SELECT kPlugin FROM tplugin WHERE cName = 'Barzahlen'";
    $pluginId = $GLOBALS["DB"]->executeQuery($sql, 1);
    $sql = "SELECT kPluginSprachvariable FROM tpluginsprachvariable WHERE cName = '".$state."' AND kPlugin = '".$pluginId->kPlugin."'";
    $langId = $GLOBALS["DB"]->executeQuery($sql, 1);
    $sql = "SELECT cISO FROM tsprache WHERE cStandard = 'Y' LIMIT 1";
    $lang = $GLOBALS["DB"]->executeQuery($sql, 1);
    $sql = "SELECT cName FROM tpluginsprachvariablesprache WHERE kPluginSprachvariable = '".$langId->kPluginSprachvariable."' AND cISO = '".strtoupper($lang->cISO)."'";
    $language = $GLOBALS["DB"]->executeQuery($sql, 1);

    $text = $language->cName != '' ? $language->cName : $state;

    $newState = 'Barzahlen ('.$text.')';
    $sql = "UPDATE tbestellung SET cZahlungsartName = '".$newState."' WHERE kBestellung = '".$kBestellung."' ";
    $GLOBALS["DB"]->executeQuery($sql, 1);

    $sql = "UPDATE tbestellung SET cAbgeholt = 'N' WHERE kBestellung = '".$kBestellung."' ";
    $GLOBALS["DB"]->executeQuery($sql, 1);
  }
}
?>