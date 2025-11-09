<html>
<head>
<title> Non-Seamless-kit</title>
</head>
<body>
<center>

<?php include('Crypto.php')?>
<?php 

	error_reporting(0);
	
	$working_key='';//Shared by CCAVENUES
	$access_code='';//Shared by CCAVENUES
	
	//using session for demo purpose. For production, store values in Database
	$_SESSION['order']['order_id'] = $_POST['order_id'];
	$_SESSION['order']['amount'] = $_POST['amount'];
	$_SESSION['order']['currency'] = $_POST['currency'];
	
	$merchant_data='';
	foreach ($_POST as $key => $value){
		$merchant_data.=$key.'='.urlencode($value).'&';
	}

	$encrypted_data=encrypt($merchant_data,$working_key); // Method for encrypting the data.

?>
<form method="post" name="redirect" action="https://spayuattrns.bmtest.om/transaction.do?command=initiateTransaction"> 
<?php
echo "<input type=hidden name=encRequest value=$encrypted_data>";
echo "<input type=hidden name=access_code value=$access_code>";
?>
</form>
</center>
<script language='javascript'>document.redirect.submit();</script>
</body>
</html>

