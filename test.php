<?php
	date_default_timezone_set('Europe/Prague');
	require_once 'fio.api.php';
	require_once 'fio.render.php';

	$fio = new FioApi(''); // API TOKEN FROM FIO BANK
	$fioRender = new FioRender();
	$fio->reset();
	$transactions = $fio->getData();
	$transactionsParsed = $fio->getParsedArray($transactions);

	echo $fioRender->getHTMLHeader();
	echo $fioRender->getTransactionList($transactionsParsed, 'prijmy', 'Příjmy');
	echo $fioRender->getTransactionList($transactionsParsed, 'nakupy', 'Nákupy');
	echo $fioRender->getTransactionList($transactionsParsed, 'bankomaty', 'Bankomaty');
	echo $fioRender->getTransactionList($transactionsParsed, 'dobijeni', 'Dobíjení');
	echo $fioRender->getTransactionList($transactionsParsed, 'poplatky', 'Poplatky');
	echo $fioRender->getTransactionList($transactionsParsed, 'ostatniVydaje', 'Ostatní výdaje');
	echo $fioRender->getSummary($transactionsParsed);
	echo $fioRender->getHTMLFooter();


