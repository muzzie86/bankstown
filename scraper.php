<?php
### Bankstown City Council scraper

require 'scraperwiki.php'; 
require 'simple_html_dom.php';

date_default_timezone_set('Australia/Sydney');

$url_base = "http://eplanning.bankstown.nsw.gov.au";
$da_page = $url_base . "/ApplicationSearch/ApplicationSearchThroughLodgedDate?day=thismonth";        # Use this URL to get 'This Month' submitted DA
#$da_page = $url_base . "/ApplicationSearch/ApplicationSearchThroughLodgedDate?day=lastmonth";        # Use this URL to get 'Last Month' submitted DA

$mainUrl = scraperWiki::scrape("$da_page");
$dom = new simple_html_dom();
$dom->load($mainUrl);

# Just focus on the 'td' section of the web site and this give us idea on how many records by calculation
$dataset = $dom->find("td[width=85%] div");

# The usual, look for the data set and if needed, save it
for ($i=1; $i <= (count($dataset)/2)-1; $i++) {
    # Slow way to transform the date but it works
    $date_received = explode('<br />', $dom->find("td[width=85%] div div", ($i-1))->innertext);
    $date_received = explode(' ', trim($date_received[0]));
    $date_received = explode('/', trim($date_received[1]));
    $date_received = "$date_received[2]-$date_received[1]-$date_received[0]";
    
    # Prep some data before hand
    $tempstr = explode('</h4>', $dataset[($i-1)*2]->innertext);
    $tempstr = explode('<br />', $tempstr[1]);
    $desc = preg_replace('/\s+/', ' ', trim($tempstr[0]));
    $desc = explode(' - ', $desc);
    $desc = html_entity_decode($desc[1]);
    $addr = explode('</b>', $tempstr[1]);
    $addr = substr(trim($addr[0]), 13);        

    # Put all information in an array
    $application = array (
        'council_reference' => trim($dom->find("td[width=85%] div a", $i-1)->plaintext),
        'address' => $addr . "  AUSTRALIA",
        'description' => $desc,
        'info_url' => $url_base . trim($dom->find("td[width=85%] div a", $i-1)->href),
        'comment_url' => $url_base . trim($dom->find("td[width=85%] div a", $i-1)->href),
        'date_scraped' => date('Y-m-d'),
        'date_received' => date('Y-m-d', strtotime($date_received))
    );

    # Check if record exist, if not, INSERT, else do nothing
    $existingRecords = scraperwiki::select("* from data where `council_reference`='" . $application['council_reference'] . "'");
    if (count($existingRecords) == 0) {
        print ("Saving record " . $application['council_reference'] . "\n");
        # print_r ($application);
        scraperwiki::save(array('council_reference'), $application);
    } else {
        print ("Skipping already saved record " . $application['council_reference'] . "\n");
    }
}


?>
