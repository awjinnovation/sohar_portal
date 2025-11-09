<?php include('Crypto.php')?>
<?php

	error_reporting(0);
	
	$workingKey='';		//Working Key should be provided here.
	$encResponse=$_POST["encResp"];			//This is the response sent by the CCAvenue Server
	$rcvdString=decrypt($encResponse,$workingKey);		//Crypto Decryption used as per the specified working key.
	$order_status="";
	$decryptValues=explode('&', $rcvdString);
	$dataSize=sizeof($decryptValues);
	echo "<center>";

	$dataArr = array();
	foreach ($decryptValues as $key => $value) {
		$orderdata = explode('=', $value);
		$dataArr[$orderdata[0]] = $orderdata[1];
	}

	$order_status = $dataArr['order_status'];

	if($order_status==="Success") {
		if ($dataArr['order_id'] == $_SESSION['order']['order_id'] && $dataArr['currency'] == $_SESSION['order']['currency'] && round($dataArr['amount'],2) == round($_SESSION['order']['amount'],2)) {
			echo "<br>Thank you for shopping with us. Your credit card has been charged and your transaction is successful. We will be shipping your order to you soon.";
		} else {
			echo "<br>Security Error. Illegal access detected";
		}
		
	} else if($order_status==="Aborted") {
		echo "<br>Payment has been aborted";
	} else if($order_status==="Failure") {
		echo "<br>Payment has been failed.";
	} else {
		echo "<br>Payment has been failed. Please try again.";
	}

	echo "<br><br>";

	echo "<table cellspacing=4 cellpadding=4>";
	for($i = 0; $i < $dataSize; $i++) 
	{
		$information=explode('=',$decryptValues[$i]);
	    	echo '<tr><td>'.$information[0].'</td><td>'.$information[1].'</td></tr>';
	}

	echo "</table><br>";
	echo "</center>";
