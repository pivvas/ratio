<?php
function _p($array, $isDie)
{
	echo '<pre>';
	print_r($array);
	echo '</pre>';

	if ($isDie) {
		die;
	}
}
