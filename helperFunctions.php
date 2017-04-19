<?php
	if (isset($_POST['action'])) {
		switch ($_POST['action']) {
			case 'Submit':
				$result =  queryAmazon($_POST['searchKey']);
				echo $result["ASIN"] . ";" . $result["Title"] . ";" . $result["MPN"] . ";" . $result["Price"] ;
				break;
			case 'InsertData':
				insertData($_POST['asin'],$_POST['title'],$_POST['mpn'],$_POST['price']);
				echo "Data inserted";
				break;
			case 'showData':
				showData();
				break;
		}
	}
	
	function insertData($asin,$title,$mpn,$price)
	{	
		$servername = "localhost";
		$username = "root";
		$password = "";
		$dbname = "apadvert";

		// Create connection
		$conn = new mysqli($servername, $username, $password, $dbname);
		// Check connection
		if ($conn->connect_error) {
			die("Connection failed: " . $conn->connect_error);
		} 
		if (strpos($title, "'") !== false) {
			$title = addslashes($title);
		}
		$sql = "INSERT INTO products (ASIN, Title, MPN, Price) VALUES ( '" . $asin . "','" . $title . "','" . $mpn . "','" . $price . "');" ;
		echo $sql;
		$result = $conn->query($sql);
		$conn->close();	
	}
	
	
	function showData()
	{
		$servername = "localhost";
		$username = "root";
		$password = "";
		$dbname = "apadvert";

		// Create connection
		$conn = new mysqli($servername, $username, $password, $dbname);
		// Check connection
		if ($conn->connect_error) {
			die("Connection failed: " . $conn->connect_error);
		} 

		$sql = "SELECT * FROM products";
		$result = $conn->query($sql);
		$tableData = "";
		if ($result->num_rows > 0) {
			// output data of each row
			$tableData = $tableData . "<table cellpadding=\"10\" border=\"3\">";
			$tableData = $tableData . "<tr><th>ASIN</th>"; 
			$tableData = $tableData . "<th>Title</th>";
			$tableData = $tableData . "<th>MPN</th>";
			$tableData = $tableData . "<th>Price</th></tr>";
			while($row = $result->fetch_assoc()) {	
				$tableData = $tableData . "<tr><td>" . $row["ASIN"]. "</td>"; 
				$tableData = $tableData . "<td>" . $row["Title"]. "</td>";
				$tableData = $tableData . "<td>" . $row["MPN"]. "</td>";
				$tableData = $tableData . "<td>" . $row["Price"]. "</td></tr>";
			}
			$tableData = $tableData . "</table>";
		} else {
			echo "0 results";
		}
		$conn->close();	
		echo $tableData;
	}
	
	function queryAmazon($asin)
    {
		$parameters = array("Operation"     => "ItemLookup",
                            "ItemId"        => $asin,
                            "ResponseGroup" => "Medium");
		$xml_response = aws_signed_request("com",
                                  $parameters,
                                  "AKIAIOWFZ4KTTJAKNLFQ",
                                  "DL6rUpqfXpMuQEVmiGGYgudKa0ePlbaR8OX4OjHB",
                                  "q0d9b-20");
		
		$result = array("ASIN"  => $xml_response->Items->Item->ASIN,
                        "Title" => $xml_response->Items->Item->ItemAttributes->Title,
						"MPN" => $xml_response->Items->Item->ItemAttributes->MPN,
						"Price" => $xml_response->Items->Item->OfferSummary->LowestNewPrice->FormattedPrice
						);
	
		print_r($xml_response);
		echo "<br>";
		print_r($result);
		return 	$result;				  	
    }
	
	
	function  aws_signed_request($region,
                             $params,
                             $public_key,
                             $private_key,
                             $associate_tag)
	{
	 
		$method = "GET";
		$host = "ecs.amazonaws.".$region;
		$uri = "/onca/xml";
	 
	 
		$params["Service"]          = "AWSECommerceService";
		$params["AWSAccessKeyId"]   = $public_key;
		$params["AssociateTag"]     = $associate_tag;
	 
		$params["Timestamp"]        = gmdate("Y-m-d\TH:i:s\Z");
		$params["Version"]          = "2009-03-31";
	 
		/* The params need to be sorted by the key, as Amazon does this at
		  their end and then generates the hash of the same. If the params
		  are not in order then the generated hash will be different from
		  Amazon thus failing the authentication process.
		*/
		ksort($params);
	 
		$canonicalized_query = array();
	 
		foreach ($params as $param=>$value)
		{
			$param = str_replace("%7E", "~", rawurlencode($param));
			$value = str_replace("%7E", "~", rawurlencode($value));
			$canonicalized_query[] = $param."=".$value;
		}
	 
		$canonicalized_query = implode("&", $canonicalized_query);
	 
		$string_to_sign = $method."\n".$host."\n".$uri."\n".
								$canonicalized_query;
	 
		/* calculate the signature using HMAC, SHA256 and base64-encoding */
		$signature = base64_encode(hash_hmac("sha256", 
									  $string_to_sign, $private_key, True));
	 
		/* encode the signature for the request */
		$signature = str_replace("%7E", "~", rawurlencode($signature));
	 
		/* create request */
		$request = "http://".$host.$uri."?".$canonicalized_query."&Signature=".$signature;
	 
		/* I prefer using CURL */
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$request);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	 
		$xml_response = curl_exec($ch);
	 
		if ($xml_response === False)
		{
			return False;
		}
		else
		{
			/* parse XML and return a SimpleXML object, if you would
			   rather like raw xml then just return the $xml_response.
			 */
			 
			$parsed_xml = @simplexml_load_string($xml_response);
			return ($parsed_xml === False) ? False : $parsed_xml;
		}
	}
	
?>
