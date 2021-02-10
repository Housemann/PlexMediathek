<?php
    
    require_once __DIR__ . '/../libs/helper_variables.php';
    require_once __DIR__ . '/../libs/helper_color.php';

    // Klassendefinition
    class PlexMediathek extends IPSModule {

        use PLEX_HelperVariables;
 
        // Überschreibt die interne IPS_Create($id) Funktion
        public function Create() {
            // Diese Zeile nicht löschen.
            parent::Create();

            $this->RegisterPropertyString ("IPAddress", "2.2.2.2");
            $this->RegisterPropertyString ("Port", "32400");
            $this->RegisterPropertyInteger ("UpdateIntervall", 60);

            $this->RegisterPropertyInteger ("ColorHeader", -1);
            $this->RegisterPropertyInteger ("FontSizeHeader", 16);
            $this->RegisterPropertyInteger ("ColorTable", -1);
            $this->RegisterPropertyInteger ("FontSizeTable", 14);
            $this->RegisterPropertyString ("BorderStyle", "outset");
            $this->RegisterPropertyInteger ("BorderWidth", 1);
            
            $this->RegisterProfileInteger("PLEX.Mediatheken", "", "", "", 0, 0, 0);
            $this->Variable_Register("Libraries", "Libraries", "PLEX.Mediatheken", "", 1, true,1 ,true);
            $this->Variable_Register("MediaHTMLBox", "Mediathek", "~HTMLBox", "", 3, "", 2);
        
            $this->RegisterTimer ("UpdateMediathek", 0, 'PLEX_ReadAndFillHtmlFromPlex($_IPS[\'TARGET\'],\'Libraries\');');

        }
 
        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() {
            // Diese Zeile nicht löschen
            parent::ApplyChanges();

            // Timer zum Mediathek Update
            $this->SetTimerInterval("UpdateMediathek", $this->ReadPropertyInteger("UpdateIntervall") * 60 * 1000);

            // HTML Box neu laden
            $this->ReadAndFillHtmlFromPlex ("Libraries");

        }
 

        public function RequestAction($Ident, $Value) 
        {
            switch($Ident) {
                case "Libraries":
                    SetValue($this->GetIDForIdent($Ident), $Value);
                    $this->ReadAndFillHtmlFromPlex ($Ident);
                break;
                default:
                throw new Exception("Invalid Ident");
            }
        }

        // Grundfunktion die Daten zusammenstellt
        public function ReadAndFillHtmlFromPlex (string $Ident) 
        {
            $ip = $this->ReadPropertyString("IPAddress");
            $port = $this->ReadPropertyString("Port");

            $rc = $this->CheckIpAdressPortStatus($ip, $port);
            if($rc['ErrorCode']==0) {

                // Profil assoziationen aktualisieren
                $this->CreateMediaAssoziations();
                
                // VarId zu Ident holen
                $FormattedValueMediathek = GetValueFormatted($this->GetIDForIdent($Ident));

                // Plex Daten für Mediathek auslesen
                $ret_array = $this->GetPlexMetaData ($FormattedValueMediathek);
                
                // HTML Box füllen
                $this->FillHtmlBox("MediaHTMLBox", $ret_array);
            
            } else {
                return $rc['ErrorCode'];
                IPS_LogMessage(IPS_GetName($_IPS['SELF'])." (". $_IPS['SELF'].")", "(".$rc['ErrorCode'].")"." ".$rc['ErrorMsg'] );
            }
        }


        // Metadaten für Mediathek auslesen und in arra schreiben
        private function GetPlexMetaData (string $FormattedValueMediathek) 
        {
            $ip = $this->ReadPropertyString("IPAddress");
            $port = $this->ReadPropertyString("Port");
            
            // Zum Formatted Ident z.B. BluRay den Plex Mediakey holen weil in Array vom Ident falsch ist
            $arrayMappedKey = $this->ReadMediaAssoziationMappedKey($FormattedValueMediathek); // return array 'key' 'title' 'type'

            // Mediathek über Plex Mediathek ID auslesen aus Auswahl
            $arrayMediathek = $this->ReadXmlFileMedia($arrayMappedKey[0]['key']);

            // Meidathek durchgehen und Anzahl der Medien zaehlen um Array zu fuellen 
            foreach($arrayMappedKey as $value) {
              $count_all_sections = 0;
              if($FormattedValueMediathek === $value['title'] && $value['type'] === "show") {
                if($arrayMediathek['@attributes']['size']==1) {
                    $count_all_sections=$arrayMediathek['@attributes']['size'];
                } else {
                    $count_all_sections = count($arrayMediathek['Directory']);
                }
                $SetValueArray = "Directory";
              } elseif($FormattedValueMediathek === $value['title'] && $value['type'] === "movie") {
                if($arrayMediathek['@attributes']['size']==1) {
                    $count_all_sections=$arrayMediathek['@attributes']['size'];
                } else {
                    $count_all_sections = count($arrayMediathek['Video']);
                }  
                $SetValueArray = "Video";
              } elseif($FormattedValueMediathek === $value['title'] && $value['type'] === "artist") {
                if($arrayMediathek['@attributes']['size']==1) {
                    $count_all_sections=$arrayMediathek['@attributes']['size'];
                } else {
                    $count_all_sections = count($arrayMediathek['Directory']);
                }                    
                $SetValueArray = "Directory";
              } elseif($FormattedValueMediathek === $arrayMediathek['@attributes']['title1'] && $value['type'] === "photo") {
                if($arrayMediathek['@attributes']['size']==1) {
                    $count_all_sections=$arrayMediathek['@attributes']['size'];
                } else {
                    $count_all_sections = count($arrayMediathek['Directory']);
                }                    
                $SetValueArray = "Directory";
              }

              // Array abhaengig von Anzahl fuellen 
              $array=array();
              if($count_all_sections>1) {
                  for($i = 0; $i < $count_all_sections; $i++) {
                      $array[] = array (
                          "thumb"		=>	'http://'.$ip.':'.$port.@$arrayMediathek[$SetValueArray][$i]['@attributes']['thumb'],
                          "title"		=>	@$arrayMediathek[$SetValueArray][$i]['@attributes']['title'],
                          "type"		=>	@$arrayMediathek[$SetValueArray][$i]['@attributes']['type'],
                          "summary"		=>	@$arrayMediathek[$SetValueArray][$i]['@attributes']['summary'],
                          "addedAt"		=>	date("d.m.Y", @$arrayMediathek[$SetValueArray][$i]['@attributes']['addedAt']),
                          "year"		=>	@$arrayMediathek[$SetValueArray][$i]['@attributes']['year']
                      );
                  }
              } else {
                    $array[] = array (
                        "thumb"		=>	'http://'.$ip.':'.$port.@$arrayMediathek[$SetValueArray]['@attributes']['thumb'],
                        "title"		=>	 @$arrayMediathek[$SetValueArray]['@attributes']['title'],
                        "type"		=>	@$arrayMediathek[$SetValueArray]['@attributes']['type'],
                        "summary"	=>	@$arrayMediathek[$SetValueArray]['@attributes']['summary'],
                        "addedAt"	=>	date("d.m.Y", @$arrayMediathek[$SetValueArray]['@attributes']['addedAt']),
                        "year"		=>	@$arrayMediathek[$SetValueArray]['@attributes']['year']
                    );  
              }
            }
            return $array;
        }


        // HTML Box füllen
        private function FillHtmlBox(string $Ident, $array) {
            
            $type = $array[0]['type'];

            $color_header       = str_replace("0x","#",$this->IntToHex($this->ReadPropertyInteger ("ColorHeader")));
            $font_size_header 	= $this->ReadPropertyInteger ("FontSizeHeader")."px";

            $color_table        = str_replace("0x","#",$this->IntToHex($this->ReadPropertyInteger ("ColorTable")));
            $font_size_table 	= $this->ReadPropertyInteger ("FontSizeTable")."px";

            $border_style       = $this->ReadPropertyString ("BorderStyle"); // dotted,dashed,solid,double,groove,ridge,inset,outset,none,hidden
            $border_width       = $this->ReadPropertyInteger ("BorderWidth")."px";
            


            $s = '';
            $s = $s . "<style type='text/css'>";
            $s = $s . "table.test { width: 100%; border-collapse: true;}";
            $s = $s . "CSS { border: 1px solid #444455; border-style: $border_style; border-width: $border_width;}</style>";
            $s = $s . "<table class='CSS'>";

            $s = $s . "<tr>";
            $s = $s . "<th style='border-width: $border_width; border-style: $border_style; text-align:cwnter;background: $color_header;font-size:$font_size_header;' colspan='2'><B>Cover</td>";

            if($type === "artist") {
                $s = $s . "<th style='border-width: $border_width; border-style: $border_style; width: 20px;text-align:left;background: $color_header;font-size:$font_size_header;' colspan='2'><B>Artist</td>";
            } elseif($type === "show") {
                $s = $s . "<th style='border-width: $border_width; border-style: $border_style; width: 20px;text-align:left;background: $color_header;font-size:$font_size_header;' colspan='2'><B>Serie</td>";
            } elseif($type === "movie") {
                $s = $s . "<th style='border-width: $border_width; border-style: $border_style; width: 20%;text-align:left;background: $color_header;font-size:$font_size_header;' colspan='2'><B>Film</td>";
            } elseif($type === "photo") {
                $s = $s . "<th style='border-width: $border_width; border-style: $border_style; width: 20%;text-align:left;background: $color_header;font-size:$font_size_header;' colspan='2'><B>Fotoalbum</td>";
            }

            $s = $s . "<th style='border-width: $border_width; border-style: $border_style; text-align:center;background: $color_header;font-size:$font_size_header;'width=150px; colspan='2'><B>Jahr</td>";

            if($type !== "photo") {
                $s = $s . "<th style='border-width: $border_width; border-style: $border_style; background: $color_header; font-size:$font_size_header;' colspan='2'><B>Beschreibung</td>";

            }

            $s = $s . "<th style='border-width: $border_width; border-style: $border_style; text-align:center;background: $color_header;font-size:$font_size_header;'width=250px; colspan='2'><B>Hinzugefügt</td>";
            
            $s = $s . "</tr>";
            $s = $s . "<tr>";

            foreach($array as $key) {
                $type		= $key['type'];

                if(!empty($key['thumb'])) {
                    if($type === "artist") {
                        $pic = "<img src=".$key['thumb']." width=\"150\" height=\"150\" >";
                    } elseif($type === "photo") {
                        $pic = "<img src=".$key['thumb']." width=\"150\" height=\"150\" >";
                    } else {
                        $pic = "<img src=".$key['thumb']." width=\"130\" height=\"200\" >";
                    }
                } else {
                    $pic = "";
                }
                $title 	= $key['title'];

                if($key['summary']=="" && $type === "photo") {
                    $summary = "";
                } else {
                    $summary = $key['summary'];
                }
                
                $addedAt	= $key['addedAt'];
                
                if($key['year']=="") {
                    $year	= substr($key['addedAt'],-4,4);
                } else {
                    $year   = $key['year'];
                }

                $s = $s . "<tr>";
                $s = $s . "<td style='border-width: $border_width; border-style: $border_style; text-align:center; background: $color_table; font-size:$font_size_table;' colspan='2'>$pic</td>";
                $s = $s . "<td style='border-width: $border_width; border-style: $border_style; background: $color_table; font-size:$font_size_table;' colspan='2'>$title</td>";
                $s = $s . "<td style='border-width: $border_width; border-style: $border_style; text-align:center; background: $color_table;font-size:$font_size_table;' colspan='2'>$year</td>";

                if($type !== "photo") {
                    $s = $s . "<td style='border-width: $border_width; border-style: $border_style; text-align:left; background: $color_table; font-size:$font_size_table;' colspan='2'>$summary</td>";
                }

                $s = $s . "<td style='border-width: $border_width; border-style: $border_style; text-align:center; background: $color_table; font-size:$font_size_table;' colspan='2'>$addedAt</td>";
                $s = $s . "</tr>";
                $s = $s . "<tr>";
            }
            SetValue($this->GetIDForIdent($Ident), $s);
        }


        // Mediatheken auslesen und in Variablenprofil schreiben
        private function CreateMediaAssoziations() 
        {
            $ip = $this->ReadPropertyString("IPAddress");
            $port = $this->ReadPropertyString("Port");

            $arrayLib = $this->ReadXmlFileLibraries();
            
            $count_xmlData = count($arrayLib['Directory']);

            // Daten holen für sortierung
            $arrayMedia=array();
            for ($i = 0; $i < $count_xmlData; $i++) {
              $title = $arrayLib['Directory'][$i]['@attributes']['title'];
              $arrayMedia[]=$title;
            }
            asort($arrayMedia);
            $arrayMedia=array_values($arrayMedia); #Array neu nummerieren

            if(!empty($arrayMedia)) {
                #Assoziationen immer vorher leeren
                $GetVarProfile = IPS_GetVariableProfile("PLEX.Mediatheken");
                foreach($GetVarProfile['Associations'] as $assi ) {
                  @IPS_SetVariableProfileAssociation ("PLEX.Mediatheken", $assi['Value'], "", "", 0 );
                }
                
                // ProfileAssociations füllen
                foreach($arrayMedia as $Key => $Value) {
                    IPS_SetVariableProfileAssociation ("PLEX.Mediatheken", $Key, $Value, "", -1);
                }
            }
        }

        // Mediathekname aus Integer Variable anhand des Namens ummappen und den Plex MediathekKey holen
        private function ReadMediaAssoziationMappedKey(string $value) 
        {
            // Formatted Wert aus Libraries ummappen zu Plex Mediathek
            $ip = $this->ReadPropertyString("IPAddress");
            $port = $this->ReadPropertyString("Port");

            $arrayLib = $this->ReadXmlFileLibraries();

            $count_xmlData = count($arrayLib['Directory']);
            $arrayMedia=array();
            for ($i = 0; $i < $count_xmlData; $i++) {
              $arrayMedia[]= array( "key"   => $arrayLib['Directory'][$i]['@attributes']['key'],
                                    "title" => $arrayLib['Directory'][$i]['@attributes']['title'],
                                    "type"  => $arrayLib['Directory'][$i]['@attributes']['type']
                                    );
            }

            // Key zu Mediathek mit dem Namen suchen
            foreach($arrayMedia as $values) {
                if($values['title']==$value) {
                    return $array = array(array("key"    => $values['key'],
                                                "title"  => $values['title'],
                                                "type"   => $values['type']
                    ));
                    break;
                }
            } 
        }

        // Bibliotheken in Plex auslesen
        private function ReadXmlFileLibraries() 
        {
            $ip = $this->ReadPropertyString("IPAddress");
            $port = $this->ReadPropertyString("Port");
            
            $Sections = simplexml_load_file('http://'.$ip.':'.$port.'/library/sections'); 
            $array_xml_sections = json_decode(json_encode($Sections),true);

            return $array_xml_sections;
        }

        // Einzelne Bibliothek auslesen
        private function ReadXmlFileMedia(string $id) 
        {
            $ip = $this->ReadPropertyString("IPAddress");
            $port = $this->ReadPropertyString("Port");
            
            $Sections_all = simplexml_load_file('http://'.$ip.':'.$port.'/library/sections/'.$id.'/all'); 
            $array_xml_sections_all = json_decode(json_encode($Sections_all),true);

            return $array_xml_sections_all;
        }

        // Helper Funktion für Formular prüfung gülte Adresse
        public function URL_Valid() 
        {
            $ip = $this->ReadPropertyString("IPAddress");
            $port = $this->ReadPropertyString("Port");

            $msg = $this->CheckIpAdressPortStatus($ip, $port);
            echo '('.$msg['ErrorCode'].')'.' '.$msg['ErrorMsg']; 
            return $msg;
        }

        private function IntToHex (int $value) {
            $HEX = sprintf('0x%06X',$value);
            return $HEX;
          }
    }