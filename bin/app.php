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

if (is_array($_GET) && isset($_GET['action']))
{
    echo "<pre>";

//    echo ROOT;
//    echo "<br><br><br>";
    print_r($_GET);
//    echo "<br><br><br>";
//    echo $_GET['action'];

    switch ($_GET['action'])
    {
        case "setup_zpsXLK47AqaTPjZuuyBM":
            //http://currency.rem.zp.ua/bin/app.php?action=setup_zpsXLK47AqaTPjZuuyBM

            echo "<br><br><br>";
            echo "Create DB structure";

//            $DB->exec("DROP TABLE rates;");
//            $DB->exec("DROP TABLE notice;");
//
//            $DB->exec("CREATE TABLE rates (
//            id          INTEGER PRIMARY KEY,
//            update_time INT,
//            currency    CHAR (3),
//            buy         REAL,
//            sell        REAL
//            )");
//
//            $DB->exec("CREATE TABLE notice (
//            id          INTEGER PRIMARY KEY,
//            update_time INT,
//            currency    CHAR (3),
//            buy         REAL,
//            sell        REAL,
//            email       TEXT
//            )");

            break;
        case 'zpsXLK47AqaTPjZuuyBM_cron':
            UpdateRates();
            break;
        case 'zpsXLK47AqaTPjZuuyBM_test':


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
            break;
        default:
            break;
    }

    echo "</pre>";

}



if (is_array($_POST) && isset($_POST['action']))
{

    $aAnswer = array(
        'status'    => 1,
        'data'      => array(),
    );

    switch ($_POST['action'])
    {
        case "read_rates":
            UpdateRates();

            $oResult = $DB->query("SELECT * FROM rates ORDER BY id DESC LIMIT 2");

            while ($aResult = $oResult->fetchArray(SQLITE3_ASSOC))
            {
                $fBuy   = $aResult['buy'];
                $fSell  = $aResult['sell'];
                $aAnswer['data']['rates'][$aResult['currency']]["name"]     = $aResult['currency'];
                $aAnswer['data']['rates'][$aResult['currency']]["buy"]      = $fBuy;
                $aAnswer['data']['rates'][$aResult['currency']]["sell"]     = $fSell;
                $aAnswer['data']['rates'][$aResult['currency']]["spread_a"] = $fSell-$fBuy;
                $aAnswer['data']['rates'][$aResult['currency']]["spread_r"] = round(($fSell-$fBuy)/$fSell*100,2);
            }

            $iDayBegin = mktime( 0,0,0 );

            $aAnswer['debug']['daybegin'] = $iDayBegin;

            $oResult = $DB->query("SELECT currency, sum(buy) AS buy, sum(sell) AS sell, count(id) AS count FROM rates WHERE update_time > ".round($iDayBegin * 1000)." GROUP BY currency");

            while ($aResult = $oResult->fetchArray(SQLITE3_ASSOC))
            {
                $aAnswer['data']['rates'][$aResult['currency']]["average"] = round( ($aResult['buy']+$aResult['sell'])/($aResult['count']*2) ,2);
            }

            if (count($aAnswer['data']) == 0)
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


            break;
        default:
            break;
    }

    echo json_encode($aAnswer);
}
