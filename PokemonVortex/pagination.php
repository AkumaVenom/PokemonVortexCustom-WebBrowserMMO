<?php
	$adjacents = 3;
	
	/* 
	   First get total number of rows in data table. 
	   If you have a WHERE clause in your query, make sure you mirror it here.
	*/
	$query = "SELECT COUNT(*) FROM $table_used $query_used";
	$total_pages = mysql_fetch_row(mysql_query($query));
	$total_pages = $total_pages[0];
	
	/* Setup vars for query. */
	$limit = 30; 								//how many items to show per page
	if($page) 
		$start = ($page - 1) * $limit; 			//first item to display on this page
	else
		$start = 0;								//if no page var is given, set start to 0
	
	/* Get data. */
	
	/* Setup page vars for display. */
	if ($page == 0) $page = 1;					//if no page var is given, default to 1.
	$prev = $page - 1;							//previous page is page - 1
	$next = $page + 1;							//next page is page + 1
	$lastpage = ceil($total_pages/$limit);		//lastpage is = total pages / items per page, rounded up.
	$lpm1 = $lastpage - 1;						//last page minus 1
	
	$pagination = "";
	if($lastpage > 1)
	{	
		$pagination .= "";
		//previous button
		if ($page > 1) 
			$pagination.= '<a href="' . $php_page . '?' . $page_name . '&page=' . $prev . '" onclick="get(\'' . $php_page . '\',\'' . $page_name . '&page=' . $prev . '\'); return false;" class="deselected">« Previous</a>';
		else
			$pagination.= '<a href="#" class="deselected">« Previous</a>';	
		
		//pages	
		if ($lastpage < 7 + ($adjacents * 2))	//not enough pages to bother breaking it up
		{	
			for ($counter = 1; $counter <= $lastpage; $counter++)
			{
				if ($counter == $page)
					$pagination.= '<a href="#" class="selected">' . $counter . '</a>';
				else
					$pagination.= '<a href="' . $php_page . '?' . $page_name . '&page=' . $counter . '" onclick="get(\'' . $php_page . '\',\'' . $page_name . '&page=' . $counter . '\'); return false;" class="deselected">' . $counter . '</a>';					
			}
		}
		elseif($lastpage > 5 + ($adjacents * 2))	//enough pages to hide some
		{
			//close to beginning; only hide later pages
			if($page < 1  + ($adjacents * 2))		
			{
				for ($counter = 1; $counter < 4 + ($adjacents * 2); $counter++)
				{
					if ($counter == $page)
						$pagination.= '<a href="#" class="selected">' . $counter . '</a>';
					else
						$pagination.= '<a href="' . $php_page . '?' . $page_name . '&page=' . $counter . '" onclick="get(\'' . $php_page . '\',\'' . $page_name . '&page=' . $counter . '\'); return false;" class="deselected">' . $counter . '</a>';					
				}
				$pagination.= "...";
				$pagination.= '<a href="' . $php_page . '?' . $page_name . '&page=' . $lpm1 . '" onclick="get(\'' . $php_page . '\',\'' . $page_name . '&page=' . $lpm1 . '\'); return false;" class="deselected">' . $lpm1 . '</a>';
				$pagination.= '<a href="' . $php_page . '?' . $page_name . '&page=' . $lastpage . '" onclick="get(\'' . $php_page . '\',\'' . $page_name . '&page=' . $lastpage . '\'); return false;" class="deselected">' . $lastpage . '</a>';		
			}
			//in middle; hide some front and some back
			elseif($lastpage - ($adjacents * 2) > $page && $page > ($adjacents * 2))
			{
				$pagination.= '<a href="' . $php_page . '?' . $page_name . '&page=1" onclick="get(\'' . $php_page . '\',\'' . $page_name . '&page=1\'); return false;" class="deselected">1</a>';
				$pagination.= '<a href="' . $php_page . '?' . $page_name . '&page=2" onclick="get(\'' . $php_page . '\',\'' . $page_name . '&page=2\'); return false;" class="deselected">2</a>';
				$pagination.= "...";
				for ($counter = $page - $adjacents; $counter <= $page + $adjacents; $counter++)
				{
					if ($counter == $page)
						$pagination.= '<a href="#" class="selected">' . $counter . '</a>';
					else
						$pagination.= '<a href="' . $php_page . '?' . $page_name . '&page=' . $counter . '" onclick="get(\'' . $php_page . '\',\'' . $page_name . '&page=' . $counter . '\'); return false;" class="deselected">' . $counter . '</a>';					
				}
				$pagination.= "...";
				$pagination.= '<a href="' . $php_page . '?' . $page_name . '&page=' . $lpm1 . '" onclick="get(\'' . $php_page . '\',\'' . $page_name . '&page=' . $lpm1 . '\'); return false;" class="deselected">' . $lpm1 . '</a>';
				$pagination.= '<a href="' . $php_page . '?' . $page_name . '&page=' . $lastpage . '" onclick="get(\'' . $php_page . '\',\'' . $page_name . '&page=' . $lastpage . '\'); return false;" class="deselected">' . $lastpage . '</a>';		
			}
			//close to end; only hide early pages
			else
			{
				$pagination.= '<a href="' . $php_page . '?' . $page_name . '&page=1" onclick="get(\'' . $php_page . '\',\'' . $page_name . '&page=1\'); return false;" class="deselected">1</a>';
				$pagination.= '<a href="' . $php_page . '?' . $page_name . '&page=2" onclick="get(\'' . $php_page . '\',\'' . $page_name . '&page=2\'); return false;" class="deselected">2</a>';
				$pagination.= "...";
				for ($counter = $lastpage - (2 + ($adjacents * 2)); $counter <= $lastpage; $counter++)
				{
					if ($counter == $page)
						$pagination.= '<a href="#" class="selected">' . $counter . '</a>';
					else
						$pagination.= '<a href="' . $php_page . '?' . $page_name . '&page=' . $counter . '" onclick="get(\'' . $php_page . '\',\'' . $page_name . '&page=' . $counter . '\'); return false;" class="deselected">' . $counter . '</a>';					
				}
			}
		}
		
		//next button
		if ($page < $counter - 1) 
			$pagination.= '<a href="' . $php_page . '?' . $page_name . '&page=' . $next . '" onclick="get(\'' . $php_page . '\',\'' . $page_name . '&page=' . $next . '\'); return false;" class="deselected">Next »</a>';
		else
			$pagination.= '<a href="#" class="deselected">Next »</a>';
		$pagination.= "\n";		
	}
?>
