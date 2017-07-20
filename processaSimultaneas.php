<?
	include('/usr/lib/telefoniaip/includePrincipal.php');

	$dia     = isset($argv[1]) ? $argv[1]     : date('Y-m-d');
	$minSecs = isset($argv[2]) ? $argv[2] + 0 : 3;

	$sql = "SELECT extract('epoch' FROM calldate)::int AS ts,duration,billsec ".
		"FROM cdr WHERE calldate::date = '$dia' AND billsec >= $minSecs ORDER BY calldate";

	list($y,$m,$d) = explode('-', $dia);
	if(strlen($m)<2) $m="0$m";
	if(strlen($d)<2) $d="0$d";
	$dia = "$y-$m-$d";

	
	$simults      = 0;
	$simultsMedia = 0;

	$chamadas = array();
	$minTS = time();
	$maxTS = 0;

	$minSimult  = 1000000;
	$maxSimult  = 0;
	$minSimultM = 1000000;
	$maxSimultM = 0;

	$minSimultPH  = array();
	$maxSimultPH  = array();
	$minSimultMPH = array();
	$maxSimultMPH = array();

	echo "Buscando chamadas do dia '$dia' com duracao minima de $minSecs s:\n";
	$conn = new Conexao();
	$conn->executa($sql);
	$totCh = $conn->qtd;
	echo "Total => $totCh\n";

	// buscar array de CDRs
	while($conn->temMaisDados(true)) {
		$cdr = $conn->data;
		$ch  = (object)array(
			'ini'  => $cdr->ts,
			'iniM' => $cdr->ts + ($cdr->duration - $cdr->billsec),
			'fim'  => $cdr->ts + $cdr->duration
		);
		if($ch->ini < $minTS) $minTS = $ch->ini;
		if($ch->fim > $maxTS) $maxTS = $ch->fim;
		$chamadas[] = $ch;
	}
	$conn->fecha();

	//echo "Processando...\n";
	$horaAnt = -1;
	// for de segundo em segundo do dia
	for($sec=$minTS; $sec<=$maxTS; $sec++) {
		$hora = date('H', $sec);
		if($hora != $horaAnt) {
			$horaAnt = $hora;
			// inicializar as variaves desta hora
			$minSimultPH[$hora]  = $simults;
			$maxSimultPH[$hora]  = $simults;
			$minSimultMPH[$hora] = $simultsMedia;
			$maxSimultMPH[$hora] = $simultsMedia;
		}

		// Verificar inicio/termino de chamada/media
		$done = array();
		foreach($chamadas as $idx => $ch) {
			if($ch->ini  == $sec) $simults++;
			if($ch->iniM == $sec) $simultsMedia++;
			if($ch->fim  == $sec) {
				$simults--;
				$simultsMedia--;
				$done[] = $idx;
			}
			
			// Nao processar as chamadas "que nao iniciaram ainda"
			if($ch->ini > $sec+60)
				break;
		}
		// descartar as que ja usamos
		foreach($done as $idxDone)
			unset($chamadas[$idxDone]);

		// Atualizar os acumuladores
		if($simults > $maxSimult) $maxSimult = $simults;
		if($simults < $minSimult) $minSimult = $simults;
		if($simultsMedia > $maxSimultM) $maxSimultM = $simultsMedia;
		if($simultsMedia < $minSimultM) $minSimultM = $simultsMedia;

		if($simults > $maxSimultPH[$hora]) $maxSimultPH[$hora] = $simults;
		if($simults < $minSimultPH[$hora]) $minSimultPH[$hora] = $simults;
		if($simultsMedia > $maxSimultMPH[$hora]) $maxSimultMPH[$hora] = $simultsMedia;
		if($simultsMedia < $minSimultMPH[$hora]) $minSimultMPH[$hora] = $simultsMedia;
	}

	// Sumario
	echo "Simultaneas (MAX) => ligacoes:$maxSimult - sessoes:$maxSimultM\n";
	echo "\nAgrupado Por Hora:\n";
	foreach($minSimultPH as $hora => $min)
		echo "[$hora] Simultaneas => ligacoes(min:".$minSimultPH[$hora].' - max:'.$maxSimultPH[$hora].")\t\tsessoes(min:".$minSimultMPH[$hora].' - max:'.$maxSimultMPH[$hora].")\n";
	echo "\n";

	$JS = array();
	for($h=0; $h<24; $h++) {
		$hh = ($h < 10) ? "0$h" : $h;
		$JS[$h] = (object)array("data"=>"$dia $hh:00:00", "minc" => 0, "maxc" => 0, "mins" => 0, "maxs" => 0);
	}
	foreach($minSimultPH as $hora => $min)
		$JS[$hora+0] = (object)array("data"=>"$dia $hora:00:00", "minc" => $minSimultPH[$hora], "maxc" => $maxSimultPH[$hora], "mins" => $minSimultMPH[$hora], "maxs" => $maxSimultMPH[$hora]);

	echo "escrevendo data/simults-$dia.json:";
	@mkdir('data');
	file_put_contents("data/simults-$dia.json", json_encode($JS));
	echo "OK\n\n";
?>