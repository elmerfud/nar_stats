<?php

function convert_nar_xml($filename)
{
    global $_config;

    $cmd = $_config['navisccli']['command'] . " analyzer -archivedump -data /tmp/{$filename} -xml -out /tmp/{$filename}.xml";
    exec($cmd,$output,$ret);
    if ($ret == 0) {
        return true;
    }
    return false;
}

function process_XML($raw_xml)
{
    $xml = new XMLReader();
    $xml->xml($raw_xml);
    $i = 0;
    $data = array();
    while ($xml->read()) {
        switch ($xml->nodeType) {
            case XMLReader::ELEMENT:
                if ($xml->name == "sample") {
                    $i++;
                    // Grabs Date/time of sample
                    while ($xml->moveToNextAttribute()) {
                        $sample[$xml->name] = $xml->value;
                    }
                    $data[$i]['sample_date'] = implode(' ',$sample);
                    continue;
                }
                if ($xml->name == "value") {
                    unset($value);
                    while ($xml->moveToNextAttribute()) {
                        $value[$xml->name] = $xml->value;
                    }
                    $tmp_value = implode(' ',$value);
                    continue;
                }
                break;
            case XMLReader::TEXT:
                $data[$i][$tmp_value] = $xml->value;
                unset($tmp_value);
                break;
            case XMLReader::CDATA:
                throw new Exception("Error, no CDATA should be here");
        }
    }
    $xml->close();
    unset($xml,$raw_xml,$i,$sample,$value);
    return $data;
}


?>
