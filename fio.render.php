<?php

	/**
	 *
	 * FIO Render pouze vykresluje data ziskana pomoci FIOAPI
	 *
	 */
	class FioRender {
		/**
		 * @return string
		 */
		public function getHTMLHeader() {
			return '
<!DOCTYPE html>
<html lang="cs">
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<title>FioOptimalViewer by Sami</title>
<style>
#prijmy, #nakupy, #bankomaty, #poplatky, #ostatniVydaje, #dobijeni, #summary{
	display: block;
	width: 80%;
	font-size: 0.75em;
}
.row{
	width: 100%;
	border: none;
	height: 2.5em;
}
.cell{
	border: 1px solid green;
	display: inline-block;
	text-align: center;
}
.row .cell{
	width: 15%;
}
.cell.span{
	width: 100%;
	border: none;
	font-weight: bold;
}
.text-left{
	text-align: left !important;
}
.text-right{
	text-align: right !important;
}
.popis{
	width: 40% !important;
}
</style>
</head>
<body>
';
		}

		/**
		 * @param $transakce
		 * @param $keyId - prijmy, nakupy aj.
		 * @param $title
		 *
		 * @return string
		 */
		public function getTransactionList($transakce, $keyId, $title){
			$subtotal = 0;
			$transactionTable = '<h2>' . $title . '</h2>';
			$transactionTable .= '<div id="' . $keyId . '">';
			if($keyId != 'prijmy') $multiplier = -1;
			else $multiplier = 1;
			foreach($transakce[$keyId]['transakce'] as $key => $value){
				foreach($value as $key2 => $value2){
					$transactionTable .= '<div class="row"><div class="cell span text-left">Období ' . $key . ' / ' . $key2 . '</div></div>';
					$transactionTable .= '<div class="row"><div class="cell">Datum</div><div class="cell">Částka</div><div class="cell popis">Popis</div><div class="cell">ID</div></div>';
					foreach($value[$key2] as $key3 => $value3) {
						$subtotal += $value3['castka'];
						$transactionTable .= '<div class="row"><div class="cell">'.date('d.m.Y', strtotime($value3['datum'])).'</div><div class="cell">'.$value3['castka'].' Kč</div><div class="cell popis">'.((isset($value3['popis'])) ? $value3['popis'] : ' ').'</div><div class="cell">'.$value3['ident'].'</div></div>';
					}
					$transactionTable .= '<div class="row"><div class="cell span text-right">Celkem za toto období : ' . $subtotal . ' Kč</div></div>';
					$transactionTable .= '<div class="row"><div class="cell span"> </div></div>';
					$subtotal = 0;
				}
			}
			$transactionTable .= '<div class="row"><div class="cell span text-right">Celkem za všechna období : ' . $transakce[$keyId]['celkem']*$multiplier . ' Kč</div></div>';
			$transactionTable .= '<div class="row"><div class="cell span"> </div></div>';
			$transactionTable .= '</div>';
			return $transactionTable;
		}

		/**
		 * @param $transakce
		 *
		 * @return string
		 */
		public function getSummary($transakce) {
			$summary = '<h2>REKAPITULACE</h2>';
			$summary .= '<div id="summary">';
			$summary .= '<div class="row"><div class="cell span text-right">Celkem příjmy za všechny období : ' . $transakce['celkemPrijmy'] . ' Kč</div></div>';
			$summary .= '<div class="row"><div class="cell span text-right">Celkem výdaje za všechny období : ' . $transakce['celkemVydaje']*-1 . ' Kč</div></div>';
			$summary .= '<div class="row"><div class="cell span text-right">Rozdíl : ' . ($transakce['celkemPrijmy'] - $transakce['celkemVydaje']) . ' Kč</div></div>';
			$summary .= '<div class="row"><div class="cell span"> </div></div>';
			$summary .= '</div>';
			return $summary;
		}

		/**
		 * @return string
		 */
		public function getHTMLFooter(){
			return '
</body>
</html>
';
		}
	}
