<?php
include 'config.php';

/***

itunes.apple.com/us/app/#{APPNAME(LOWERCASE SPACES ARE DASHES?)}/id#{APPID}

ARTWORK IN THE PAGE:
<a href="http://itunes.apple.com/us/app/namely/id487041855?mt=8" class="artwork-link"><div class="artwork"><img width="75" height="75" alt="Namely" class="artwork" src="http://a1.mzstatic.com/us/r1000/081/Purple/v4/15/78/c7/1578c773-4b41-91d6-bef8-d20b4ee61fbe/mzl.oswrptqy.75x75-65.jpg" /><span class="mask"></span></div></a>



***/




$ch = curl_init();

$row = $dbh->query("SELECT begin_date FROM daily_raw ORDER BY begin_date DESC LIMIT 1")->fetch();

if ($row)
{
	$starttime = strtotime('+1 day', strtotime($row['begin_date']));
}
else
{
	$starttime = strtotime('-14 days');
}

$today = time();

while ($starttime < $today)
{
	process($starttime);

	$starttime = strtotime('+1 day', $starttime);
}

curl_close ($ch);

function process($time)
{
	$date = date('Ymd', $time);

	echo 'Processing ' . $date . PHP_EOL;

	global $dbh, $ch, $accounts;

	//open connection

	$sth = $dbh->prepare ("INSERT INTO `daily_raw` (`provider`, `provider_country`, `sku`, `developer`, `title`, `version`, `product_type_identifier`, `units`, `developer_proceeds`, `begin_date`, `end_date`, `customer_currency`, `country_code`, `currency_proceeds`, `apple_identifier`, `customer_price`, `promo_code`, `parent_identifier`, `subscription`, `period`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");	
			
	foreach($accounts as $account)
	{
		$fields_string = "USERNAME=" . urlencode($account['username']);
		$fields_string .= "&PASSWORD=" . urlencode($account['password']);
		$fields_string .= "&VNDNUMBER=" . $account['vndnumber'];

		$fields_string .= "&TYPEOFREPORT=Sales";
		$fields_string .= "&DATETYPE=Daily";
		$fields_string .= "&REPORTTYPE=Summary";
		$fields_string .= "&REPORTDATE=$date";
		
		$fp = fopen("$date.gz", 'w');

		//set the url, number of POST vars, POST data
		curl_setopt($ch,CURLOPT_URL, 'https://reportingitc.apple.com/autoingestion.tft');
		curl_setopt($ch,CURLOPT_POST, 7);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$fields_string);

		curl_setopt($ch, CURLOPT_FILE, $fp);

		//execute post
		$contents = curl_exec ($ch);

		//close connection

		fclose($fp);

		if (filesize("$date.gz"))
		{
			exec("gunzip $date.gz");
			

			if (($handle = fopen("$date", "r")) !== FALSE)
			{
				//throw away first line
				fgetcsv($handle, 1000, ",");

				while (($data = fgetcsv($handle, 1000, "\t")) !== FALSE)
				{
					$count = 1;
					foreach($data as $value)
					{
						if (($count == 10) || ($count == 11))
						{
							$parts = explode('/', $value);
							$value = $parts[2] . '-' . $parts[0] . '-' . $parts[1];
						}
						 
						$sth->bindValue($count, $value);
						$count++;
					}
					$sth->execute();
					echo '.';
				}
				fclose($handle);
			}
			
			unlink("$date");
		}
	}
	
	echo 'Done' . PHP_EOL;
}