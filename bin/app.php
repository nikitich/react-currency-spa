<?php
/**
 * Created by PhpStorm.
 * User: nikitich
 * Date: 05.10.15
 * Time: 13:15
 */
//error_reporting(E_ALL);
//error_reporting(E_ERROR);
//ini_set("display_errors", 1);

define('ROOT', dirname(__DIR__).'/');
define("TIMEOUT", 10);  //таймают обновлений курсов в минутах

$DB = new SQLite3(ROOT."db/main.db");

class App
{
    var $Errors = array();

    function SetupDB ()
    {
        global $DB;

        echo "<br>";
        echo "Create DB structure";

//            $DB->exec("DROP TABLE rates;");
//
//            $DB->exec("CREATE TABLE rates (
//            id          INTEGER PRIMARY KEY,
//            update_time INT,
//            currency    CHAR (3),
//            buy         REAL,
//            sell        REAL
//            )");
//

//            $DB->exec("DROP TABLE notice;");
//
//            $DB->exec("CREATE TABLE notice (
//            id          INTEGER PRIMARY KEY,
//            update_time INT,
//            currency    CHAR (3),
//            operation   CHAR (4),
//            condition   CHAR (4),
//            rate        REAL,
//            email       TEXT
//            )");

        return true;
    }

    function DebugTest()
    {
        global $DB;

        //$DB->exec("INSERT INTO rates (update_time, currency, buy, sell) VALUES ('".time()."','USD','60.5','61.3')");

        $oResult = $DB->query("SELECT * FROM rates");

        $iUpdateTime = $DB->querySingle("SELECT update_time FROM rates ORDER BY update_time DESC LIMIT 1");

        $aUSD = $DB->querySingle("SELECT * FROM rates WHERE currency = 'USD' ORDER BY update_time DESC LIMIT 1", true);
        echo "<br><br>=============USD:================<br>";
        print_r($aUSD);
        //$aResult = $oResult->fetchArray(SQLITE3_ASSOC);
//            if ($oResult->fetchArray(SQLITE3_ASSOC))
//            {
//                echo "Result is empty";
//            }

//            $cn = $oResult->columnName(1);
        echo "<br><br>=============================<br>";
        print_r((time()*1000) - $iUpdateTime);
        echo "<br><br>==========================<br>";
        while ($aResult = $oResult->fetchArray(SQLITE3_ASSOC))
        {
            print_r($aResult);
        }

        return true;
    }
    
    function ReadRates ()
    {
        global $DB;
        
        $aResult = array();
        
        $this->UpdateRates();

        $oResult = $DB->query("SELECT * FROM rates ORDER BY id DESC LIMIT 2");

        while ($aRow = $oResult->fetchArray(SQLITE3_ASSOC))
        {
            $fBuy   = $aRow['buy'];
            $fSell  = $aRow['sell'];
            $aResult[$aRow['currency']]["name"]     = $aRow['currency'];
            $aResult[$aRow['currency']]["buy"]      = $fBuy;
            $aResult[$aRow['currency']]["sell"]     = $fSell;
            $aResult[$aRow['currency']]["spread_a"] = $fSell-$fBuy;
            $aResult[$aRow['currency']]["spread_r"] = round(($fSell-$fBuy)/$fSell*100,2);
        }

        $iDayBegin = mktime( 0,0,0 );

        $oResult = $DB->query("SELECT currency, sum(buy) AS buy, sum(sell) AS sell, count(id) AS count FROM rates WHERE update_time > ".round($iDayBegin * 1000)." GROUP BY currency");

        while ($aRow = $oResult->fetchArray(SQLITE3_ASSOC))
        {
            $aResult[$aRow['currency']]["average"] = round( ($aRow['buy']+$aRow['sell'])/($aRow['count']*2) ,2);
        }

        return $aResult;
    }


    function SendNotice()
    {
        // функция отправки оповещений

        return true;
    }

    function __construct()
    {
        global $DB;

        if (is_array($_GET) && isset($_GET['action']))
        {
            echo "<pre>";

            echo "==================================<br>";
            print_r($_GET);
            echo "==================================<br>";

            switch ($_GET['action'])
            {
                case "setup_zpsXLK47AqaTPjZuuyBM":
                    //http://currency.rem.zp.ua/bin/app.php?action=setup_zpsXLK47AqaTPjZuuyBM
                    $this->SetupDB();
                    break;
                case 'zpsXLK47AqaTPjZuuyBM_cron':
                    $this->UpdateRates();
                    $this->SendNotice();
                    break;
                case 'zpsXLK47AqaTPjZuuyBM_test':
                    $this->DebugTest();
                    break;
                default:
                    break;
            }

            echo "</pre>";

        }


        if (is_array($_POST) && isset($_POST['action']))
        {
            if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest')
            {
                return false;
            }

            if (!preg_match("/^\w{4,50}$/i", $_POST['action'])) {
                return false;
            }


            $aAnswer = array(
                'status'    => 1,
                'data'      => array(),
            );

            switch ($_POST['action'])
            {
                case "read_rates":
                    $this->UpdateRates();
                    $aAnswer['data']['rates'] = $this->ReadRates();

                    if (count($aAnswer['data']['rates']) == 0)
                    {
                        $aAnswer['status'] = 0;
                    }

                    break;
                case "set_trigger":

                    $aTriger = array(
                        'currency'  => "",      // UER/USD
                        'operation' => 0,       // buy/sell
                        'rate'      => 0.00,
                        'condition' => "",      // >=, >, <, <=
                        'email'     => "",
                    );

                    //$aTriger = array();
                    if (is_array($_POST['data']))
                    {
                        foreach($_POST['data'] as $item)
                        {
                            $aTriger[$item['name']] = $item['value'];
                        }
                    }

                    $currency   = $this->ValidateVariable($aTriger['currency'], 'required|aplha|exact_length[3]', null, 'Currency value incorrect');
                    $operation  = $this->ValidateVariable($aTriger['operation'], 'required|aplha|min_length[3]|max_length[4]', null, 'Operation value incorrect');
                    $condition  = $this->ValidateVariable($aTriger['condition'], 'required|aplha|exact_length[4]', null, 'Condition value incorrect');
                    $rate       = $this->ValidateVariable($aTriger['rate'], 'min_length[1]|numeric', null, 'Rate is requried field|Rate value must be numeric');
                    $email      = $this->ValidateVariable($aTriger['email'], 'min_length[1]|valid_email', null, 'Email is requried|You must enter valid Email address');

                    if (!count($this->Errors))
                    {
                        $iCurrentTime = round(microtime(true)*1000);

                        $DB->exec("
                            INSERT INTO notice
                            (update_time, currency, operation, condition, rate, email)
                            VALUES (
                                '". $iCurrentTime ."','". $currency ."','". $operation."','".$condition."','".$rate."','".$email."'
                            )"
                        );
                    }
                    else
                    {
                        $aAnswer['status']  = 0;
                        $aAnswer['errors']  = "- ".implode("\n - ",$this->Errors);
                    }

                    $aAnswer['data'] = $aTriger;

                    break;
                default:
                    break;
            }

            echo json_encode($aAnswer);
        }

        return true;
    }

    /**
     * Выполняет валидацию переменных.
     * Каюсь, содрано с CodeIgniter Form Validation. Но это самый быстрый способ конвеерного производства велосипедов.
     *
     * @param mixed $mVar - переменная, которую надо проверить
     * @param string $sRules - перечень правил разделенных "|". См тут: http://ellislab.com/codeigniter/user-guide/libraries/form_validation.html#rulereference
     * @param mixed $mDefaultValue - что будет возвращено, если переменной нет (null) и по правилам она не требуется (required)
     * @param string $sErrorText - текст ошибки, который пойдет в $this->Errors

     * @return mixed:
     *   - возвращает саму переменную, если прошла все проверки;
     *   - возвращает значение по умолчанию;
     *   - false.
     */
    function ValidateVariable($mVar, $sRules, $mDefaultValue=null, $sErrorText='')
    {

        $bError = false;
        $bIsNull= (!isset($mVar) || is_null($mVar));

        $aRules = explode('|', $sRules);
        $aError = explode('|', $sErrorText);

        if (count($aRules) != count($aError))
        {
            $aError = false;
        }

        foreach ($aRules as $key => $sRule)
        {
            $sParam = null;
            if (preg_match("/(.*?)\[(.*)\]/", $sRule, $aMatch))
            {
                $sRule	= $aMatch[1];
                $sParam	= $aMatch[2];
            }
            switch ($sRule)
            {
                case 'required':
                    if ($bIsNull)
                    {
                        $bError = true;
                    }
                    break;
                case 'matches':
                    if ($mVar != $sParam)
                    {
                        $bError = true;
                    }
                    break;
                case 'min_length':
                    if (preg_match("/[^0-9]/", $sParam))
                    {
                        $bError = true;
                    }
                    if (function_exists('mb_strlen'))
                    {
                        if (mb_strlen($mVar) < $sParam)
                        {
                            $bError = true;
                        }
                    }
                    if (strlen($mVar) < $sParam)
                    {
                        $bError = true;
                    }
                    break;
                case 'max_length':
                    if (preg_match("/[^0-9]/", $sParam))
                    {
                        $bError = true;
                    }
                    if (function_exists('mb_strlen'))
                    {
                        if (mb_strlen($mVar) > $sParam)
                        {
                            $bError = true;
                        }
                    }
                    if (strlen($mVar) > $sParam)
                    {
                        $bError = true;
                    }
                    break;
                case 'exact_length':
                    if (preg_match("/[^0-9]/", $sParam))
                    {
                        $bError = true;
                    }
                    if (function_exists('mb_strlen'))
                    {
                        if (mb_strlen($mVar) != $sParam)
                        {
                            $bError = true;
                        }
                    }
                    if (strlen($mVar) != $sParam)
                    {
                        $bError = true;
                    }
                    break;
                case 'greater_than':
                    if (!is_numeric($sParam))
                    {
                        $bError = true;
                    }
                    //if ($mVar < $sParam)
                    if (bccomp($mVar, $sParam) != 1)
                    {
                        $bError = true;
                    }
                    break;
                case 'greater_or_equal_than':
                    if (!is_numeric($sParam))
                    {
                        $bError = true;
                    }
                    //if ($mVar < $sParam)
                    if (bccomp($mVar, $sParam) < 0)
                    {
                        $bError = true;
                    }
                    break;
                case 'less_than':
                    if (!is_numeric($sParam))
                    {
                        $bError = true;
                    }
                    //if ($mVar > $sParam)
                    if (bccomp($mVar, $sParam) != -1)
                    {
                        $bError = true;
                    }
                    break;
                case 'less_or_equal_than':
                    if (!is_numeric($sParam))
                    {
                        $bError = true;
                    }
                    //if ($mVar > $sParam)
                    if (bccomp($mVar, $sParam) > 0)
                    {
                        $bError = true;
                    }
                    break;
                case 'alpha':
                    if (!preg_match("/^([a-z])+$/i", $mVar))
                    {
                        $bError = true;
                    }
                    break;
                case 'alpha_numeric':
                    if (!preg_match("/^([a-z0-9])+$/i", $mVar))
                    {
                        $bError = true;
                    }
                    break;
                case 'alpha_dash':
                    if (!preg_match("/^([-a-z0-9_-])+$/i", $mVar))
                    {
                        $bError = true;
                    }
                    break;
                case 'numeric':
                    if (!is_numeric($mVar))
                    {
                        $bError = true;
                    }
                    break;
                case 'integer':
                    if (!preg_match('/^[\-+]?[0-9]+$/', $mVar))
                    {
                        $bError = true;
                    }
                    break;
                case 'digits':
                    if (!preg_match('/^[0-9]+$/', $mVar))
                    {
                        $bError = true;
                    }
                    break;
                case 'decimal':
                    if (!preg_match('/^[\-+]?[0-9]+\.[0-9]+$/', $mVar))
                    {
                        $bError = true;
                    }
                    break;
                case 'is_natural':
                    if (!((bool)preg_match('/^[0-9]+$/', $mVar)))
                    {
                        $bError = true;
                    }
                    break;
                case 'is_natural_no_zero':
                    if (!((bool)preg_match('/^[0-9]+$/', $mVar)))
                    {
                        $bError = true;
                    }
                    if ($mVar == 0)
                    {
                        $bError = true;
                    }
                    break;
                case 'valid_email':
                    if (!filter_var($mVar, FILTER_VALIDATE_EMAIL))
                    {
                        $bError = true;
                    }
                    break;
                case 'valid_ip':
                    $bIsIPv4 = filter_var($mVar, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
                    $bIsIPv6 = filter_var($mVar, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
                    if (empty($bIsIPv4) && empty($bIsIPv6))
                    {
                        $bError = true;
                    }
                    break;
                case 'valid_base64':
                    if (!((bool)preg_match('/[^a-zA-Z0-9\/\+=]/', $mVar)))
                    {
                        $bError = true;
                    }
                    break;
                case 'in_list':
                    $aArray = explode(',', $sParam);
                    if (!in_array($mVar, $aArray))
                    {
                        $bError = true;
                    }
                    break;
                case 'trim':
                    if (!$bIsNull)
                    {
                        $mVar = trim($mVar);
                    }
                    break;
                case 'urldecode':
                    if (!$bIsNull)
                    {
                        $mVar = urldecode($mVar);
                    }
                    break;
                case 'htmlspecialchars':
                    if (!$bIsNull)
                    {
                        $iQuoteStyle= ENT_COMPAT;
                        $sCharset   = "UTF-8";
                        if (!empty($sParam))
                        {
                            list($sQuoteStyle, $sCharset) = explode('.', $sParam);
                            switch ($sQuoteStyle)
                            {
                                case 'ENT_QUOTES':
                                    $iQuoteStyle = ENT_QUOTES;
                                    break;
                                case 'ENT_NOQUOTES':
                                    $iQuoteStyle = ENT_NOQUOTES;
                                    break;
                                default:
                                    $iQuoteStyle = ENT_COMPAT;
                                    break;
                            }
                        }
                        $mVar = htmlspecialchars($mVar, $iQuoteStyle, $sCharset);
                    }
                    break;
                case 'sum':
                    if (!$bIsNull) {
                        $mVar = bcadd(str_replace(',', '.', $mVar), 0, 2);
                    }
                    break;
                default:
                    if (strpos($sRule, 'callback_') === 0)
                    {
                        $sCallbackFunction = substr($sRule, 9);
                        $aParams = explode(',', $sParam);
                        array_unshift($aParams, $mVar);
                        $bResult = $this->$sCallbackFunction($aParams);
                        if (!$bResult)
                        {
                            $bError = true;
                        }
                    }
                    break;
            }
            if ($bError == true)
            {
                if ($aError !== false && is_array($aError))
                {
                    $this->Errors[] = $aError[$key];
                }

                break;
            }
        }

        if (($bError == true && !$bIsNull) || ($bIsNull && is_null($mDefaultValue)))
        {
            if ($aError === false)
            {
                $this->Errors[] = $sErrorText;
            }

            return false;
        }

        return ($bIsNull)?$mDefaultValue:$mVar;
    }

    function UpdateRates()
    {
        global $DB;

        $iCurrentTime = round(microtime(true)*1000);

        $iUpdateTime = $DB->querySingle("SELECT update_time FROM rates ORDER BY update_time DESC LIMIT 1");

        if (empty($iUpdateTime) || ($iCurrentTime-$iUpdateTime) > (TIMEOUT*1000*60) )
        {
            $json = file_get_contents('https://www.tinkoff.ru/api/v1/currency_rates/');
            $obj = json_decode($json, true);

            foreach ($obj['payload']['rates'] as $item)
            {
                if ($item['category'] == "DebitCardsTransfers" && in_array($item['fromCurrency']['code'],array(840, 978)) &&  $item['toCurrency']['code'] == 643)
                {

                    $DB->exec("INSERT INTO rates (update_time, currency, buy, sell) VALUES ('". $obj['payload']['lastUpdate']['milliseconds'] ."','". $item['fromCurrency']['name'] ."','". $item['buy'] ."','".$item['sell']."')");

                }
            }

        }

        return true;
    }


}







$app = new App();

