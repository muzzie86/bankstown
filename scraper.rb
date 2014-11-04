require 'scraperwiki'
require 'mechanize'
# Moved this scraper from https://github.com/openaustralia/planningalerts-parsers/blob/master/scrapers/bankstown_scraper.rb

require 'open-uri'
require 'nokogiri'

url = "http://online.bankstown.nsw.gov.au/Planning/pages/xc.track/SearchApplication.aspx?o=html&d=lastmonth&k=LodgementDate&t=%23396&ss=a"

agent = Mechanize.new
page = agent.get(url)
page.forms.first.submit

page = agent.get(url)

page.search('.result').each do |application|
  # Skip multiple addresses
  next unless application.search("strong").select{|x|x.inner_text != "Approved"}.length == 1

  address = application.search("strong").first


  more_data = application.children[10].inner_text.split("\r\n")
  more_data[2].strip!
  
  application_id = application.search('a').first['href'].split('?').last
  info_url = "http://online.bankstown.nsw.gov.au/Planning/Pages/XC.Track/SearchApplication.aspx?id=#{application_id}"
  record = {
    "council_reference" => application.search('a').first.inner_text,
    "description" => application.children[4].inner_text.gsub("Development Application                            - ",""),
    "date_received" => Date.parse(more_data[2][0..9], 'd/m/Y').to_s,
    # TODO: There can be multiple addresses per application
    "address" => application.search("strong").first.inner_text.strip!,
    "date_scraped" => Date.today.to_s,
    "info_url" => info_url,
    # Can't find a specific url for commenting on applications.
    "comment_url" => info_url,
  }
  # DA03NY1 appears to be the event code for putting this application on exhibition
  e = application.search("Event EventCode").find{|e| e.inner_text.strip == "DA03NY1"}
  if e
    record["on_notice_from"] = Date.parse(e.parent.at("LodgementDate").inner_text).to_s
    record["on_notice_to"] = Date.parse(e.parent.at("DateDue").inner_text).to_s
  end
 
  if (ScraperWiki.select("* from data where `council_reference`='#{record['council_reference']}'").empty? rescue true)
    ScraperWiki.save_sqlite(['council_reference'], record)
  else
    puts "Skipping already saved record " + record['council_reference']
  end
end