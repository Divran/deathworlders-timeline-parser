<?php

	function openFolder( $path, &$result = array() ) {
		$files = scandir( $path );

		foreach( $files as $file ) {
			if ($file == ".") {continue;}
			if ($file == "..") {continue;}
			if ($file == "_index.md") {continue;}

			$extension = pathinfo($file, PATHINFO_EXTENSION);

			if ($extension == "") {
				openFolder( $path . "/" . $file, $result );
			} elseif ($extension == "md") {
				$result[] = substr($path,2) . "/" . $file; // substr to remove the "./"
			}
		}

		return $result;
	}

	$files = openFolder( "." );

	//echo "<pre>";

	$date_matchers = array(
		'/(\d*) years?, (\d*) months?, (\d*) weeks?, (\d*) days?/i' 	=> '$1 years $2 months $3 weeks $4 days',
		'/(\d*) years?, (\d*) months?, (\d*) weeks?/i' 					=> '$1 years $2 months $3 weeks',
		'/(\d*) years? and (\d*) months?/i' 							=> '$1 years $2 months',
		'/(\d*)y ?(\d*)m ?(\d*)w ?(\d*)d/i'								=> '$1 years $2 months $3 weeks $4 days',
		'/(\d*)y ?(\d*)m ?(\d*)w/i' 									=> '$1 years $2 months $3 weeks',
		'/(\d*)y ?(\d*)m/i' 											=> '$1 years $2 months',
		'/(\d*)y ?(\d*)m ?(\d*)d/i' 									=> '$1 years $2 months $3 days',
		'/(\d*)y ?(\d*)d/i' 											=> '$1 years $2 days',
		'/(\d*)y ?(\d*)w/i' 											=> '$1 years $2 weeks',
		'/(\d*)y/i' 													=> '$1 years',
	);

	$result = array();

	echo "<pre>";

	foreach( $files as $file ) {
		$contents = file_get_contents( $file );
		//echo "Checking " . $file . "\n";

		$date_points = array();
		
		// split around "___"
		$subchapters = preg_split('/\v[\-\-\-]|[___]\v/', $contents);
		foreach( $subchapters as $idx => $subchapter ) {

			//echo "---\n";

			// First strip out the body of the chapter, only look at the first 6 lines
			$first_lines = array();
			if (!preg_match('/(.*?)\v(.*?)\v(.*?)\v(.*?)\v(.*?)\v(.*?)\v/',$subchapter,$first_lines)) {continue;}
			$subchapter = $first_lines[0];

			// look for "Date Point"
			$matches = array();
			if (!preg_match_all('/\*\*\*?(.*?)\*?\*\*/i', $subchapter, $matches )) {continue;}

			$matched_areas = $matches[0];
			$matched_results = $matches[1];

			$date_point = $matched_results[0];
			$unparsed_date_point = $date_point;

			if ($date_point == "???") {continue;}

			$title = "";
			$character = "";

			if (isset($matched_results[1])) {$title = $matched_results[1];}
			if (isset($matched_results[2])) {$character = $matched_results[2];}

			// Erase a bunch of annoying characters
			$date_point = preg_replace('/[,~\.]/',"",$date_point);

			// Extract AV/BV, and convert it to "-" or "+"
			$av_bv = array();
			if (preg_match('/ ?([ab]v|after vancouver|before vancouver)/i',$date_point,$av_bv)) {
				$av_bv = $av_bv[1];
				if ($av_bv == "Before Vancouver" || $av_bv == "BV") {
					$av_bv = "-";
				} else {
					$av_bv = "+";
				}
			} else {
				$av_bv = "+"; // Assume it's positive
			}

			// Erase "Date Point: " from date
			$date_point = preg_replace('/date point: ?/i',"",$date_point);
			// Erase AV/BV from date
			$date_point = preg_replace('/ ?([ab]v|after vancouver|before vancouver)/i',"",$date_point);

			//echo "unparsed: " . $unparsed_date_point . "\n";
			//echo "parsed so far: " . $date_point . "\n";
			//echo "AVBV: " . $av_bv . "\n";

			//echo "Date point found:\n";
			//echo json_encode( $matches, JSON_PRETTY_PRINT ) . "\n";

			// Match the date against a bunch of patterns to parse it
			foreach( $date_matchers as $pattern => $string ) {
				$parser_matches = array();
				if (preg_match( $pattern, $date_point, $parser_matches )) {
					$date_point = preg_replace( $pattern, $string, $parser_matches[0] );
					break;
				}
			}

			//echo "Date point, after replace: '" . $date_point . "'\n";

			$time = strtotime($av_bv . $date_point);
			if ($time !== false) {
				$dt_now = DateTime::createFromFormat('U',strtotime("now"));
				$dt_then = DateTime::createFromFormat('U',$time);
				$diff = $dt_then->diff($dt_now);
				$date_point = $diff->format("%Y years %M months %D days");
				//echo "Date found: " . $date_point . "\n";
			} else {
				//echo ">>>>>>>>UNABLE TO PARSE DATE<<<<<<<<<\n";
				$date_point = "";
			}

			$date_points[] = array(
				"subchapter" => $idx,
				"subchapter_title" => $title,
				"subchapter_character" => $character,
				"date_point" => $date_point,
				"unparsed_date_point" => $unparsed_date_point
			);
		}

		$result[] = array(
			"date_points" => $date_points,
			"file" => $file
		);
	}


	echo json_encode($result,JSON_PRETTY_PRINT);
	echo "</pre>";

	file_put_contents( "output.json", json_encode($result, JSON_PRETTY_PRINT) );

?>
