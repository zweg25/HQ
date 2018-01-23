<?php

	//UPLOAD IMAGE TO SERVER
	$target_path = "./";
	$target_path = $target_path . basename($_FILES['image']['name']);
	if (filesize($_FILES['image']['tmp_name']) > 1000 && move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
	    //echo "The file ".  basename( $_FILES['image']['name'])." has been uploaded\n";
	} else {
		die("There was an error uploading the file\n");
	}

	// Key for Google Vision API
	$VISION_API_KEY = "ENTER YOUR API KEY HERE";

	// Keys for Google Custom Search URLs
	$GCSE_API_KEY = "ENTER YOUR API KEY HERE";
	$GCSE_SEARCH_ENGINE_ID = "ENTER YOUR SEARCH ENGINE ID HERE";

	// URL of hosted script
	$HOST_URL = "www.url.com/screenshot.png";


	//DETECT TEXT WITH JSON REQUEST TO GOOGLE
	$JSON_TEST = '{
	      "requests": [
	        {
	          "image": {
	            "source": {
	              "imageUri": "'.$HOST_URL.'"
	            }
	          },
	          "features": [
	            {
	              "type": "TEXT_DETECTION",
	              "maxResults": 1
	            }
	          ]
	        }
	      ]
	    }';

	/*
		NOTE: IF YOU ARE HOSTING ON LOCALHOST, 
		YOU WILL NEED TO SEND THE IMAGE TO GOOGLE THROUGH THE FOLLOWING
		COMMENTED OUT WAY. IT IS SLIGHTLY SLOWER.
	*/

	/*
	$base64 = base64_encode(file_get_contents($target_path));
	    $JSON_TEST = '{
	      "requests": [
	        {
	          "image": {
	              "content": "'.$base64.'"
	          },
	          "features": [
	            {
	              "type": "TEXT_DETECTION",
	              "maxResults": 1
	            }
	          ]
	        }
	      ]
	    }';
	*/

	// REQUEST TEXT FROM GOOGLE VISION
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "https://vision.googleapis.com/v1/images:annotate?key=".$VISION_API_KEY);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
	curl_setopt($ch, CURLOPT_POSTFIELDS, $JSON_TEST);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$server_output = curl_exec($ch);



	// GET TEXT OUTPUT
	$resp = json_decode($server_output, true);
	$text = $resp["responses"][0]["textAnnotations"][0]["description"];
	$text = strtolower($text);

	// Error check
	if (!$text || $text == "")
		die("There was an unexpected error");

	list($question, $listofanswers) = explode('?', $text, 2);

	// Does question contain the word "not" or "never"
	$isNotQuestion = (stripos($question, " not ") !== FALSE || stripos($question, " never ") !== FALSE);
	if ($isNotQuestion){
		echo "NOT QUESTION\n\n";
	}

	// Initialize array for overall answer
	$overallAnswer = array();

	// Parse question for better results
	$question = str_replace("which of these", "what", $question);
	$question = str_replace(" not ", " ", $question);
	$question = str_replace(" never ", " ", $question);
	$question = str_replace("\n", " ", $question);
	//echo $question . "?" . "\n\n";

	// Parse answers

    	// Replace " / " with " and "
    	// Common occurance in questions
    	$listofanswers = str_replace(" / ", " and ", trim($listofanswers));
	$answers = explode("\n", $listofanswers);

	// UNCOMMENT BELOW IF THERE ARE UNEXPECTED ISSUES
	/*if (count($answers) >= 4) {
	    $answers = array_values(array_filter(explode("\n", preg_replace('/\d/', '', trim($listofanswers)))));
	}
	if (count($answers) <= 1) {
	    $answers = explode(" ", trim($listofanswers));
	}*.

// ------------------- ATTEMPT 1: GOOGLE QUESTION -------------------
	$query1 = $question . "?";
	$url1 = "https://www.googleapis.com/customsearch/v1?key=" . $GCSE_API_KEY . "&cx=" . $GCSE_SEARCH_ENGINE_ID . "&q=" . urlencode($query1);

	// Send request googling question
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
	$result1 = curl_exec($ch);
	$result1 = json_decode($result1, true);

	$items = $result1['items'];

	// variables for searching
	$checksites = 0;
	$totalSnips = "| ";
	$nodes = array();

	// For each result check the text of 3 full sites
	// And count result in snippets
	foreach ($items as $item) {
    	// Get snippet
        $snippet = $item['snippet'];
        $totalSnips .= strtolower($snippet);

        // Get link for each node
        $link = $item['link'];
        if ($checksites < 3) { //&& strpos($link, "en.wikipedia.org") === FALSE
            $nodes[] = $link;
            $checksites++;
        }
    }

    // CONDUCT MULTI CURL REQUESTS FOR OPTIMIZATION
    $node_count = count($nodes);
    $curl_arr = array();
    $master = curl_multi_init();
    for ($i = 0; $i < $node_count; $i++) {
        $url = $nodes[$i];
        $curl_arr[$i] = curl_init($url);
        curl_setopt($curl_arr[$i], CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_arr[$i], CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_3) AppleWebKit/536.11 (KHTML, like Gecko) Chrome/20.0.1132.11 Safari/536.11');
        curl_setopt($curl_arr[$i], CURLOPT_HEADER, 0);
        curl_setopt($curl_arr[$i], CURLOPT_CONNECTTIMEOUT_MS, 210);
        curl_setopt($curl_arr[$i], CURLOPT_TIMEOUT_MS, 210);
        curl_multi_add_handle($master, $curl_arr[$i]);
    }
    do {
        curl_multi_exec($master, $running);
    } while ($running > 0);

    // GET RESULTS
    for ($i = 0; $i < $node_count; $i++) {
        $data = curl_multi_getcontent($curl_arr[$i]);
        if ($data) {
            $totalSnips.= strtolower($data);
        }
    }

    // Parse which answer has most results!
    $totalSnips = strtolower($totalSnips);
    $answer_results1 = array();
    foreach ($answers as $ans) {
    	$answer_results1[$ans] = substr_count($totalSnips, strtolower($ans));
    	//print $ans . " " . substr_count($totalSnips, strtolower($ans)) . "\n"; //debugging
    }

    // Figure out best answer (for questions that contain "not" that means the worst answer)
    $largestResult1 = $isNotQuestion ? min($answer_results1) : max($answer_results1);
    $totalResults1 = array_sum($answer_results1);
    $key1 = array_search($largestResult1, $answer_results1);

    echo "1. Googling Question: ";
    if ($largestResult1 > 0){
    	$percent1 = $isNotQuestion ? (100 - round(100 * $largestResult1 / $totalResults1)) : round(100 * $largestResult1 / $totalResults1);
    	printf("'%s' %d %d%% \n", $key1, $largestResult1, $percent1);
    } else {
    	echo "Inconclusive\n";
    }

    foreach ($answer_results1 as $ans => $count) {
    	$percentage = 0;
    	// Avoid division by zero
    	if ($totalResults1 != 0)
    		$percentage = $isNotQuestion ? (100 - round(100 * $count / $totalResults1)) : round(100 * $count / $totalResults1);
    	
    	if (array_key_exists($ans, $overallAnswer))
    	{
    		$overallAnswer[$ans]["count"] += $count;
    		$overallAnswer[$ans]["percent"] += $percentage;
    	} 
    	else
    	{
    		$overallAnswer[$ans] = array("answer" => $ans, "count" => $count, "percent" => $percentage);
    	}
    }



// ------------------- ATTEMPT 2+3: COUNT RESULTS + GOOGLE EACH ANSWER -------------------
    // SET UP MULTI CURL FOR SPEED
    $nodes = array();
    for ($j = 0; $j < count($answers); $j++) {
        $query = $question . "? " . $answers[$j];
        $url = "https://www.googleapis.com/customsearch/v1?key=" . $GCSE_API_KEY . "&cx=" . $GCSE_SEARCH_ENGINE_ID . "&q=" . urlencode($query);

        // ADD EACH URL TO MULTICURL ARRAY
        $nodes[] = $url;
    }

    // CONDUCT MULTICURL FOR OPTIMIZATION
    $node_count = count($nodes);
    $curl_arr = array();
    $master = curl_multi_init();
    for ($i = 0;$i < $node_count;$i++) {
        $url = $nodes[$i];
        $curl_arr[$i] = curl_init($url);
        curl_setopt($curl_arr[$i], CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_arr[$i], CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($curl_arr[$i], CURLOPT_CONNECTTIMEOUT, 2);
        curl_multi_add_handle($master, $curl_arr[$i]);
    }
    do {
        curl_multi_exec($master, $running);
    } while ($running > 0);

    $results = array();
    for ($i = 0;$i < $node_count;$i++) {
        $result = curl_multi_getcontent($curl_arr[$i]);

        // ADD RESULT TO ARRAY
        $results[] = json_decode($result, true);
    }


    $totalSnips = "";
    $countResults = array();

    // PARSE RESULTS
    for ($j = 0; $j < count($results); $j++) {
    	// Get Result
        $JSON_Result = $results[$j];
        $items = $JSON_Result['items'];
        $countResults[$answers[$j]] = $JSON_Result['searchInformation']['totalResults'];

        // Only use 4 sites, that are not wikipedia (wikipedia skews answers)
        $k = 0;

        // SET UP MULTI CURL FOR SPEED
        $nodes = array();
        foreach ($items as $item) {
        	// Get snippet
            $snippet = $item['snippet'];
            $totalSnips.= strtolower($snippet);

            // Get link for each node
            $link = $item['link'];
            if (strpos($link, "en.wikipedia.org") === FALSE) {
                $nodes[] = $link;
                $k++;
            } 

            if ($k >= 4) {
                break;
            }
        }


        // CONDUCT MULTI CURL REQUESTS
        $node_count = count($nodes);
        $curl_arr = array();
        $master = curl_multi_init();
        for ($i = 0; $i < $node_count; $i++) {
            $url = $nodes[$i];
            $curl_arr[$i] = curl_init($url);
            curl_setopt($curl_arr[$i], CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl_arr[$i], CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_3) AppleWebKit/536.11 (KHTML, like Gecko) Chrome/20.0.1132.11 Safari/536.11');
            curl_setopt($curl_arr[$i], CURLOPT_HEADER, 0);
            curl_setopt($curl_arr[$i], CURLOPT_CONNECTTIMEOUT_MS, 260);
            curl_setopt($curl_arr[$i], CURLOPT_TIMEOUT_MS, 260);
            curl_multi_add_handle($master, $curl_arr[$i]);
        }
        do {
            curl_multi_exec($master, $running);
        } while ($running > 0);

        // GET RESULTS
        for ($i = 0; $i < $node_count; $i++) {
            $data = curl_multi_getcontent($curl_arr[$i]);
            if ($data) {
                $totalSnips.= strtolower($data);
            }
        }
    }

    // Figure out best answer (for questions that contain "not" that means the worst answer)
    $largestResult2 = $isNotQuestion ? min($countResults) : max($countResults);
    $totalResults2 = array_sum($countResults);
    $percent2 = $isNotQuestion ? (100 - round(100 * $largestResult2 / $totalResults2)) : round(100 * $largestResult2 / $totalResults2);
    $key2 = array_search($largestResult2, $countResults);

    // Collect attempt 2 for overall answer
    foreach ($countResults as $ans => $count) {
    	$percentage = $isNotQuestion ? (100 - round(100 * $count / $totalResults2)) : round(100 * $count / $totalResults2);
    	
    	if (array_key_exists($ans, $overallAnswer))
    	{
    		//$overallAnswer[$ans]["count"] += round($percentage / 10);
    		$overallAnswer[$ans]["percent"] += $percentage;
    	} 
    	else
    	{
    		$overallAnswer[$ans] = array("answer" => $ans, "count" => $count, "percent" => $percentage);
    	}
    }

    echo "2. Counting Results: ";
    if ($largestResult2 > 0){
    	printf("'%s' %d %d%% \n", $key2, $largestResult2, $percent2);
    } else {
    	echo "Inconclusive\n";
    }


    // Parse which answer has most results
    $totalSnips = strtolower($totalSnips);
    $answer_results3 = array();
    foreach ($answers as $ans) {
    	$count = substr_count($totalSnips, strtolower($ans));
    	$answer_results3[$ans] = $count;
    }

    // Figure out best answer (for questions that contain "not" that means the worst answer)
    $largestResult3 = $isNotQuestion ? min($answer_results3) : max($answer_results3);
    $totalResults3 = array_sum($answer_results3);
    $key3 = array_search($largestResult3, $answer_results3);

    echo "3. Using Answers: ";
    if ($largestResult3 > 0){
    	$percent3 = $isNotQuestion ? (100 - round(100 * $largestResult3 / $totalResults3)) : round(100 * $largestResult3 / $totalResults3);
    	printf("'%s' %d %d%% \n", $key3, $largestResult3, $percent3);
    } else {
    	echo "Inconclusive\n";
    }


    foreach ($answer_results3 as $ans => $count) {
    	$percentage = 0;

    	// Avoid division by zero error
    	if ($totalResults3 > 0)
    		$percentage = $isNotQuestion ? (100 - round(100 * $count / $totalResults3)) : round(100 * $count / $totalResults3);
    	
    	if (array_key_exists($ans, $overallAnswer))
    	{
    		$overallAnswer[$ans]["count"] += $count;
    		$overallAnswer[$ans]["percent"] += $percentage;
    	} 
    	else
    	{
    		$overallAnswer[$ans] = array("answer" => $ans, "count" => $count, "percent" => $percentage);
    	}
    }

    echo "\nOVERALL ANSWER: ";

    // Calculate and print overall result
    $bestResultArr = [];

    foreach ($overallAnswer as $ans => $array) {
        $addOrSubtract = $isNotQuestion ? -1*round($array["percent"] / 30) : round($array["percent"] / 10);
    	if (array_key_exists($ans, $bestResultArr))
    	{
    		$bestResultArr[$ans] += $array["count"] + $addOrSubtract;
    	}
    	else {
    		$bestResultArr[$ans] = $array["count"] + $addOrSubtract;
    	}
    }

    // Figure out best overall answer
    $largestResult4 = $isNotQuestion ? min($bestResultArr) : max($bestResultArr);
    $totalResults4 = array_sum($bestResultArr);
    $key4 = array_search($largestResult4, $bestResultArr);

    if ($largestResult4 > 0 || $isNotQuestion){
    	$overallPercent = min($overallAnswer[$key4]["percent"] / 2, 99);
    	printf("'%s' %d%% \n", $key4, $overallPercent);
    } else {
    	echo "Inconclusive\n";
    }

     // print_r($bestResultArr);
     // print_r($overallAnswer);

