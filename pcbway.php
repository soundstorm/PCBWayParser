<?php
class PCBWay {
	private $ch;
	private $domain = "https://member.pcbway.com/";

	function __construct($ck = null) {
		if (is_null($ck)) {
			$ck = ".pcbway-".uniqid().".ck";
		}
		$this->ch = curl_init();
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/x-www-form-urlencoded',
			'Connection: keep-alive',
			'Accept-Encoding: gzip, deflate',
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
		));
		curl_setopt($this->ch,CURLOPT_ENCODING , "gzip");
		curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($this->ch, CURLOPT_COOKIEJAR, $ck);
		curl_setopt($this->ch, CURLOPT_COOKIEFILE, $ck);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, TRUE);
	}
	
	function checkLogin() {
		curl_setopt($this->ch, CURLOPT_URL, $this->domain);
		$result = curl_exec($this->ch);
		return strpos($result, 'https://login.pcbway.com/GetLoginSession.aspx') === false;
	}

	function login($email, $password) {
		curl_setopt($this->ch, CURLOPT_URL, "https://login.pcbway.com/SetSession.aspx");
		$post_fields = array('Email'=>$email, 'Pwd'=>$password, 'act'=>'login');
		curl_setopt($this->ch, CURLOPT_POST, 1);
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
		$result = curl_exec($this->ch);
		//reset
		curl_setopt($this->ch, CURLOPT_POST, 0);
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, "");
		if (preg_match("#location\.href='(.*)';#", $result, $redir)) {
			curl_setopt($this->ch, CURLOPT_URL, $redir[1]);
			return $result = curl_exec($this->ch);
		}
		return false;
	}

	function getReview() {
		curl_setopt($this->ch, CURLOPT_URL, $this->domain."Order/CartList");
		$result = curl_exec($this->ch);
		$return = array();
		preg_match_all("#<span class=\"\w+\s*icon-sprite\s*\">\s*([\w\s,]+?)\s*</span>\s*</div>\s*<div>\s*<a href=\"javascript:editpono\((\d+)#is", $result, $info);
		for ($i = 0; $i < sizeof($info[0]); $i++) {
			$return[$info[2][$i]] = array(
				'status' => $info[1][$i],
				'comment' => ''
			);
		}
		preg_match_all("#<ul class=\"item-content clearfix\">.*?<p>([\w\s\d<>/;\.\-_\+\\$\:]*)</p>\s*</div>.*?<a href=\"javascript:editpono\((\d+)#is", $result, $info);
		for ($i = 0; $i < sizeof($info[0]); $i++) {
			$return[$info[2][$i]]['comment'] = str_replace('</p><p>', ' ', $info[1][$i]);
		}
		return $return;
	}

	function getProductDetail($oid) {
		curl_setopt($this->ch, CURLOPT_URL, $this->domain."order/OrderDetail?proid={$oid}&protype=2");
		$result = curl_exec($this->ch);
		$matches = array(
			"Size" => 'size',
			"Quantity" => 'quantity',
			"Layers" => 'layers',
			"Thickness" => 'thickness',
			"Silkscreen" => 'silk',
			"Solder Mask" => 'mask',
			"Create Time" => 'time',
			"Build Time" => 'duration',
			"Manufacturing" => 'comment'
		);
		$return = array();
		foreach ($matches as $key => $value) {
			preg_match("#<b>\s*{$key}\s*:\s*</b>\s*</td>\s*<td[\scolspan=\"\d]*>\s*(.*?)\s*</td>#i", $result, $info);
			$return[$value] = $info[1];
		}
		$return['time'] = strtotime($return['time']);  //convert to ts
		$return['layers'] = $return['layers']+0;       //convert to int
		$return['thickness'] = $return['thickness']+0; //convert to float
		preg_match("#<b>\s*US\s*\\$\s*([\d\.]+)\s*</b>#i", $result, $info);
		$return['price'] = $info[1];
		preg_match("#>(.*?)\.(zip|rar)</a>#i", $result, $info);
		$return['file'] = $info[1];
		return $return;
	}
	
	function getOrders() {
		curl_setopt($this->ch, CURLOPT_URL, $this->domain."Order/OrderList?type=1");
		$result = curl_exec($this->ch);
		preg_match_all("#a href=\"/Order/GroupDetail\?GroupId=(\d+)#i", $result, $ord_match);
		$orders = array();
		for ($i = 0; $i < sizeof($ord_match[0]); $i++) {
			$orders[] = $ord_match[1][$i];
		}
		return $orders;
	}

	function getOrderDetail($oid) {
		curl_setopt($this->ch, CURLOPT_URL, $this->domain."Order/GroupDetail?GroupId={$oid}");
		$result = curl_exec($this->ch);
		preg_match_all("#a onclick=\"ViewOrder\(2,(\d+)\)#i", $result, $prod_match);
		$return = array(
			'pcbs' => array(),
			'tracking_numbers' => array(),
			'price' => array()
		);
		for ($i = 0; $i < sizeof($prod_match[0]); $i++) {
			$return['pcbs'][] = $prod_match[1][$i];
		}
		preg_match_all("#<td class=\"no\">([\d\w]+)</td>#i", $result, $track_match);
		$return['tracking_numbers'] = $track_match[1];
		preg_match("#<td class=\"product-price\" style =\"color: \#333;\">US \\$\s*([\d\.]+)\s*</td>\s*<td class=\"shipping-price\">US \\$\s*([\d\.]+)\s*</td>\s*<td class=\"discount-price\">-?US \\$\s*([\d\.]+)\s*</td>\s*<td class=\"discount-price\">-?US \\$\s*([\d\.]+)\s*</td>\s*<td class=\"fee\">US \\$\s*([\d\.]+)\s*</td>\s*<td class=\"amount\">US \\$\s*([\d\.]+)</td>#i", $result, $price_match);
		$return['price'] = array(
			"product" => $price_match[1],
			"shipping" => $price_match[2],
			"coupon" => -$price_match[3],
			"discount" => -$price_match[4],
			"paypal" => $price_match[5],
			"total" => $price_match[6]
		);
		return $return;
	}

	function getProgress($oid) {
		curl_setopt($this->ch, CURLOPT_URL, $this->domain."order/OrderStep?proid={$oid}");
		$result = curl_exec($this->ch);
		preg_match_all("#<tr>\s*<td>\s*([\w\d\s\.\(\)]+?)\s*</td>\s*<td>\s*[&nbsp;]*<img src=\"/img/images/yes.png\" />\s*</td>\s*<td>\s*[&nbsp;]*([\d]+/[\d]+/[\d]+ [\d]+:[\d]+:[\d]+)[\s\w\+\-\d]*</td>\s*</tr>#is", $result, $prog_match);
		$progress = array();
		for ($i = 0; $i < sizeof($prog_match[0]); $i++) {
			$progress[] = array(
				'step' => $prog_match[1][$i],
				'time' => strtotime($prog_match[2][$i])
			);
		}
		return $progress;
	}
}
