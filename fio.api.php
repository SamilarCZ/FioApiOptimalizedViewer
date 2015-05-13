<?php

	/**
	 *
	 * FIO Api popsane v dokumentu http://www.fio.cz/docs/cz/API_Bankovnictvi.pdf
	 *
	 * Umi pouze cist, neumi zapis
	 */
	class FioApi {
		const URL_RESET = 'https://www.fio.cz/ib_api/rest/set-last-date/%s/%s/';
		const URL_DATA = 'https://www.fio.cz/ib_api/rest/last/%s/transactions.json';
		/**
		 *
		 * Token pro prislusny ucet
		 *
		 * @var string
		 */
		protected $_token;

		/**
		 * Konstruktor pro FIO API objekt
		 *
		 * FIO Api pouziva pro pristup k uctu token, ktery se da vygenerovat
		 * v internetovem bankovnictvi.
		 *
		 *
		 * @param string $token API fio token
		 */
		public function __construct($token) {
			$this->_token = $token;
		}

		/**
		 * Vynuluje casovou znacku, od ktere se stahuji data
		 * Nutno zohlednit ROK ZALOZENI UCTU(nezadavat zde starsi datum nez je ucet), JINAK API VRACI CHYBU !!!!!
		 *
		 * @param string $resetDate Datum ke kteremu se ma vynulovat citac
		 */
		public function reset($resetDate = '2015-01-01 00:00:00') {
			$date = date_create($resetDate)->format('Y-m-d');
			$url = sprintf(self::URL_RESET, $this->_token, $date);
			file_get_contents($url);
		}

		/**
		 * Stahne data z banky
		 *
		 * Stahne data od posledni casove znacky (da se vynulovat prikazem reset).
		 * Data na vystupu jsou v nasledujicim formatu:
		 *
		 * array(
		 *   'iban' => iban uctu
		 *   'cislo' => cislo uctu (udaj pred lomitkem)
		 *   'kod_banky' => vzdy 2010
		 *   'mena' => mena dle ISO 4217, tedy CZK, USD, EUR apod
		 *   'transakce' => pole transakci ve formatu popsanem nize
		 * )
		 *
		 * Kazda transakce ma nasledujici format:
		 *
		 * array(
		 *   'protiucet' => cislo protiuctu (udaj pred lomitkem)
		 *   'banka' => kod banky protiuctu
		 *   'castka' => castka transakce, kladna = kredit, zaporna = debit
		 *   'mena' => mena dle ISO 4217, tedy CZK, USD, EUR apod
		 *   'ks' => konstantni symbol
		 *   'vs' => variabilni symbol
		 *   'ss' => specificky symbol
		 *   'datum' => datum zauctovani ve formaty Y-m-d H:i:s, tedy napr. 2013/04/03 18:30:12
		 *   'popis' => zprava pro prijemce
		 *   'popis_interni' => uzivatelska identifikace
		 *   'ident' => id transakce, unikatni v ramci uctu, pri prevodu mezi
		 *              vlastnimi ucty se muze opakovat, tedy neni samo o sobe vhodne
		 *              jako primarni klic
		 *   'typ' => slovni vyjadreni transakce
		 * )
		 *
		 * @return array
		 */
		public function getData() {
			try {
				$url = sprintf(self::URL_DATA, $this->_token);
				$data = file_get_contents($url);
				$cleanData = json_decode($data);
				return $this->_processData($cleanData);
			} catch(Exception $e) {
				echo $e->getMessage();
			}
		}

		protected function _processData($jsonData) {
			$accountInfo = $jsonData->accountStatement->info;
			$account = array(
				'iban' => $accountInfo->iban,
				'cislo' => $accountInfo->accountId,
				'kod_banky' => $accountInfo->bankId,
				'mena' => $accountInfo->currency,
				'transakce' => array()
			);
			$transactions = array();
			$cols = array(
				1 => 'castka',
				2 => 'protiucet',
				3 => 'kod_banky',
				4 => 'ks',
				5 => 'vs',
				6 => 'ss',
				7 => 'popis',
				8 => 'typ',
				9 => 'provedl',
				14 => 'mena',
				16 => 'zprava',
				17 => 'id_pokyn',
			);
			if(!is_array($jsonData->accountStatement->transactionList->transaction)) {
				$jsonData->accountStatement->transactionList->transaction = array();
			}
			foreach($jsonData->accountStatement->transactionList->transaction as $t) {
				$tr = array(
					'id' => $t->column22->value,
					'datum' => date_create($t->column0->value),
				);
				foreach($cols as $k => $v) {
					$c = $t->{'column'.$k};
					$tr[$v] = !is_null($c) ? $c->value : null;
				}
				$transactions[] = $tr;
			}
			foreach($transactions as $transaction) {
				$t = array(
					'protiucet' => $transaction['protiucet'],
					'banka' => $transaction['kod_banky'],
					'castka' => $transaction['castka'],
					'mena' => $accountInfo->currency,
					'ks' => $transaction['ks'],
					'vs' => $transaction['vs'],
					'ss' => $transaction['ss'],
					'datum' => $transaction['datum']->format('Y-m-d H:i:s'),
					'popis' => $transaction['zprava'],
					'popis_interni' => $transaction['popis'],
					'ident' => $transaction['id'],
					'typ' => $transaction['typ']
				);
				$account['transakce'][] = $t;
			}
			return $account;
		}

		public function getParsedArray($transactions) {
			$nakupy = array(
				'celkem' => 0,
				'transakce' => array()
			);
			$bankomaty = array(
				'celkem' => 0,
				'transakce' => array()
			);
			$poplatky = array(
				'celkem' => 0,
				'transakce' => array()
			);
			$prijmy = array(
				'celkem' => 0,
				'transakce' => array()
			);
			$ostatniVydaje = array(
				'celkem' => 0,
				'transakce' => array()
			);
			$dobijeni = array(
				'celkem' => 0,
				'transakce' => array()
			);
			$iNakup = 0;
			$iBankomat = 0;
			$iPoplatky = 0;
			$iPrijem = 0;
			$iOstatniVydaje = 0;
			$iDobijeni = 0;
			foreach($transactions['transakce'] as $key => $value) {
				$rok = date('Y', strtotime($value['datum']));
				$mesic = date('m', strtotime($value['datum']));
				if($value['castka'] > 0) {
					if($value['typ'] === 'Vklad pokladnou') {
						$value['popis'] = $value['typ'];
					}
					$prijmy['celkem'] += $value['castka'];
					$prijmy['transakce'][$rok][$mesic][$iPrijem]['popis'] = $value['popis'];
					$prijmy['transakce'][$rok][$mesic][$iPrijem]['castka'] = $value['castka'];
					$prijmy['transakce'][$rok][$mesic][$iPrijem]['datum'] = $value['datum'];
					$prijmy['transakce'][$rok][$mesic][$iPrijem]['ident'] = $value['ident'];
					$iPrijem++;
				} else {
					if(!isset($value['popis']) && $value['popis_interni'] !== '') {
						$value['popis'] = $value['popis_interni'];
					} elseif(!isset($value['popis']) && !isset($value['popis_interni'])) {
						$value['popis'] = ' ';
					}
					if(strpos($value['popis'], 'Nákup:') !== false) {
						$nakupy['celkem'] += $value['castka'] * -1;
						$nakupy['transakce'][$rok][$mesic][$iNakup]['popis'] = $value['popis'];
						$nakupy['transakce'][$rok][$mesic][$iNakup]['castka'] = $value['castka'];
						$nakupy['transakce'][$rok][$mesic][$iNakup]['datum'] = $value['datum'];
						$nakupy['transakce'][$rok][$mesic][$iNakup]['ident'] = $value['ident'];
						$iNakup++;
					} elseif(strpos($value['popis'], 'Výběr z bankomatu:') !== false) {
						$bankomaty['celkem'] += $value['castka'] * -1;
						$bankomaty['transakce'][$rok][$mesic][$iBankomat]['popis'] = $value['popis'];
						$bankomaty['transakce'][$rok][$mesic][$iBankomat]['castka'] = $value['castka'];
						$bankomaty['transakce'][$rok][$mesic][$iBankomat]['datum'] = $value['datum'];
						$bankomaty['transakce'][$rok][$mesic][$iBankomat]['ident'] = $value['ident'];
						$iBankomat++;
					} elseif(strpos($value['popis'], 'Zaúčtování dobití tel') !== false) {
						$dobijeni['celkem'] += $value['castka'] * -1;
						$dobijeni['transakce'][$rok][$mesic][$iDobijeni]['popis'] = $value['popis'];
						$dobijeni['transakce'][$rok][$mesic][$iDobijeni]['castka'] = $value['castka'];
						$dobijeni['transakce'][$rok][$mesic][$iDobijeni]['datum'] = $value['datum'];
						$dobijeni['transakce'][$rok][$mesic][$iDobijeni]['ident'] = $value['ident'];
						$iDobijeni++;
					} elseif(strpos($value['popis'], 'Poplatek -') !== false) {
						$poplatky['celkem'] += $value['castka'] * -1;
						$poplatky['transakce'][$rok][$mesic][$iPoplatky]['popis'] = $value['popis'];
						$poplatky['transakce'][$rok][$mesic][$iPoplatky]['castka'] = $value['castka'];
						$poplatky['transakce'][$rok][$mesic][$iPoplatky]['datum'] = $value['datum'];
						$poplatky['transakce'][$rok][$mesic][$iPoplatky]['ident'] = $value['ident'];
						$iPoplatky++;
					} elseif($value['castka'] < 0) {
						$ostatniVydaje['celkem'] += $value['castka'] * -1;
						$ostatniVydaje['transakce'][$rok][$mesic][$iOstatniVydaje]['popis'] = $value['popis'];
						$ostatniVydaje['transakce'][$rok][$mesic][$iOstatniVydaje]['castka'] = $value['castka'];
						$ostatniVydaje['transakce'][$rok][$mesic][$iOstatniVydaje]['datum'] = $value['datum'];
						$ostatniVydaje['transakce'][$rok][$mesic][$iOstatniVydaje]['ident'] = $value['ident'];
						$iOstatniVydaje++;
					}
				}
			}
			$celkemVydaje = $nakupy['celkem'] + $bankomaty['celkem'] + $dobijeni['celkem'] + $poplatky['celkem'] + $ostatniVydaje['celkem'];
			$celkemPrijmy = $prijmy['celkem'];
			return array(
				'prijmy' => $prijmy,
				'nakupy' => $nakupy,
				'bankomaty' => $bankomaty,
				'dobijeni' => $dobijeni,
				'poplatky' => $poplatky,
				'ostatniVydaje' => $ostatniVydaje,
				'celkemVydaje' => $celkemVydaje,
				'celkemPrijmy' => $celkemPrijmy
			);
		}
	}
