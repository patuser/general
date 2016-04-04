<?php
 
/*
* File: GetUSDRate.php
* Author: Michael Fleuchaus
* Date: 2009-09-03
*
*adjustments for MSSql Server JR 2012-09-27*
*adjustments for Patricia 5.4 JR 2014-07-11
* Usage: php /path/GetUSDRate.php
*/
 
//Mac Specific
//putenv("ODBCINSTINI=/Library/ODBC/odbcinst.ini");
//putenv("ODBCINI=/Library/ODBC/odbc.ini");

//Code
date_default_timezone_set('Europe/Berlin');

$xml_file = "www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml";
$adjust_percent = 0.0;
$odbc_datasource = "Patricia32";
$odbc_user = "sa";
$odbc_pass = "yourdbpassword";
$exchange_rate;
$validity_date;
$site_id; //for new stupid table row id in patricia
$db_fetch_text = "SELECT table_key FROM [dbo].[table_PRIMARY_KEYS] where table_name = 'currency_site_id'"; //fro fetching that stupid table row id
   
$currency_domain = substr($xml_file,0,strpos($xml_file,"/"));
$currency_file = substr($xml_file,strpos($xml_file,"/"));
$fp = @fsockopen($currency_domain, 80, $errno, $errstr, 10);
	if($fp) {
		$out = "GET ".$currency_file." HTTP/1.1\r\n";
		$out .= "Host: ".$currency_domain."\r\n";
		$out .= "User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8) Gecko/20051111 Firefox/1.5\r\n";
		$out .= "Connection: Close\r\n\r\n";
		fwrite($fp, $out);
		while (!feof($fp))
			{$buffer .= fgets($fp, 128);}
			
		fclose($fp);
 
		$pattern = "{<Cube\s*currency='USD'\s*rate='([\d\.]*)'/>}is";
		preg_match_all($pattern,$buffer,$xml_rate);
        
		$exchange_rate = $xml_rate[1][0];
		$validity_date = $xml_date[1][0];
		
		
		// fetch that row id from patricia
		 $conn = odbc_connect($odbc_datasource, $odbc_user, $odbc_pass);
		
		if(!$conn)
			{odbc_errormsg();}
 		else
		{
		$sql = $db_fetch_text;
         
		$rs = odbc_exec($conn, $sql);
		$site_id= odbc_result($rs,"table_key");
		$site_id= ($site_id+1);
		if(!$rs)
			{exit("Error in SQL: $rs");}
		 
		odbc_close($conn);
		}
		
		
		// continue updating
		
		$exchange_rate = 1/($exchange_rate * (1+($adjust_percent/100)));
		
		$writetime = date("Y-m-d H:i:s", time());
		
		//horribly long sql statement to insert into the new currency_site_validation table and then update the primary keys.
		
$dbText = " INSERT INTO [dbo].[CURRENCY_SITE_VALIDATION]([CURRENCY_SITE_ID], [COMPANY_ID], [SITE_ID], [CURRENCY_ID], [CURRENCY_RATE_VALID_DATE], [CURRENCY_CONV_FACTOR], [CURRENCY_EXCH_RATE], [IN_CURRENCY_EXCH_RATE], [OUT_CURRENCY_EXCH_RATE], [DEFAULT_CURRENCY_ID]) 
	VALUES($site_id, 0, 0, N'USD',  '$writetime', 1, $exchange_rate, 1, 1, 'EUR') ; UPDATE [dbo].[TABLE_PRIMARY_KEYS] 	SET [TABLE_KEY]=$site_id, [TABLE_KEY_CHANGED]='$writetime'	WHERE TABLE_NAME = 'currency_site_id'";
        print_r($dbText);
		
		print_r($db_fetch_text);
		print_r($site_id);
		
		$conn = odbc_connect($odbc_datasource, $odbc_user, $odbc_pass);
		
		if(!$conn)
			{odbc_errormsg();}
 		else
		{
		$sql = $dbText;
         
		$rs = odbc_exec($conn, $sql);
		if(!$rs)
			{exit("Error in SQL: $rs");}
		 
		odbc_close($conn);
		}
	}
?>