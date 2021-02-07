<?php
    
    require_once __DIR__ . '/../libs/helper_variables.php';

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


        public function ReadAndFillHtmlFromPlex (string $Ident) 
        {
            // Profil assoziationen aktualisieren
            $this->CreateMediaAssoziations();

            // Variable ID merken
            $VariableId = $this->GetIDForIdent($Ident);
            
            // VarId zu Ident holen
            $FormattedValueMediathek = GetValueFormatted($VariableId);

            // Plex Daten für Mediathek auslesen
            $ret_array = $this->GetPlexMetaData ($FormattedValueMediathek);
            
            // HTML Box füllen
            $this->FillHtmlBox("MediaHTMLBox", $ret_array);

        }


        // Metadaten für Mediathek auslesen und in arra schreiben
        private function GetPlexMetaData (string $FormattedValueMediathek) 
        {
            $ip = $this->ReadPropertyString("IPAddress");
            $port = $this->ReadPropertyString("Port");
            
            // Zum Formatted Ident z.B. BluRay den Plex Mediakey holen weil in Array vom Ident falsch ist
            $arrayMappedKey = $this->ReadMediaAssoziationMappedKey($FormattedValueMediathek); // return array 'key' 'title' 'type'

            // Mediathek auslesen für Auswahl
            $arrayMediathek = $this->ReadXmlFileMedia($arrayMappedKey[0]['key']);

            // Hier noch einbauen, das Fotos abgefangen werden.
            foreach($arrayMappedKey as $value) {
              $count_all_sections = 0;
              if($FormattedValueMediathek === $value['title'] && $value['type'] === "show") {
                  $count_all_sections = count($arrayMediathek['Directory']);
                  $SetValueArray = "Directory";
              } elseif($FormattedValueMediathek === $value['title'] && $value['type'] === "movie") {
                  $count_all_sections = count($arrayMediathek['Video']);
                  $SetValueArray = "Video";
              } elseif($FormattedValueMediathek === $value['title'] && $value['type'] === "artist") {
                  $count_all_sections = count($arrayMediathek['Directory']);
                  $SetValueArray = "Directory";
              }

              // Hier noch einbauen, wenn nur ein Film / Serie vorhanden ist, dass [$i] raus fliegt
              $array=array();
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
            }
            return $array;
        }


        // HTML Box füllen
        private function FillHtmlBox(string $Ident, $array) {
            $type = $array[0]['type'];

            $font_size_header 	= "16px";
            $font_size_table 	= "14px";
            $font_size_summary 	= "12px";

            $s = '';
            $s = $s . "<style type='text/css'>";
            $s = $s . "table.test { width: 100%; border-collapse: true;}";
            $s = $s . "CSS { border: 1px solid #444455; }</style>";
            $s = $s . "<table class='CSS'>";

            $s = $s . "<tr>";
            $s = $s . "<td style='text-align:left;background: #121212;font-size:$font_size_header;' colspan='2'><B>Cover</td>";

            if($type === "artist") {
                $s = $s . "<td style='width: 20%;text-align:left;background: #121212;font-size:$font_size_header;' colspan='2'><B>Artist</td>";
            } elseif($type === "show") {
                $s = $s . "<td style='width: 20%;text-align:left;background: #121212;font-size:$font_size_header;' colspan='2'><B>Serie</td>";
            } elseif($type === "movie") {
                $s = $s . "<td style='width: 20%;text-align:left;background: #121212;font-size:$font_size_header;' colspan='2'><B>Film</td>";
            }

            $s = $s . "<td style='text-align:center;background: #121212;font-size:$font_size_header;' colspan='2'><B>Jahr</td>";
            $s = $s . "<td style='background: #121212;font-size:$font_size_header;' colspan='2'><B>Beschreibung</td>";
            $s = $s . "<td style='text-align:center;background: #121212;font-size:$font_size_header;' colspan='2'><B>Hinzugefügt</td>";
            $s = $s . "</tr>";
            $s = $s . "<tr>";

            foreach($array as $key) {
                $type		= $key['type'];

                if(!empty($key['thumb'])) {
                    if($type === "artist") {
                        $pic = "<img src=".$key['thumb']." width=\"150\" height=\"150\" >";
                    } else {
                        $pic = "<img src=".$key['thumb']." width=\"130\" height=\"200\" >";
                    }
                } else {
                    $pic = "";
                }
                $title 		= $key['title'];
                $summary 	= $key['summary'];
                $addedAt	= $key['addedAt'];
                $year		= $key['year'];

                $s = $s . "<tr>";
                $s = $s . "<td style='text-align:center;font-size:$font_size_table;' colspan='2'>$pic</td>";
                $s = $s . "<td style='font-size:$font_size_table;' colspan='2'>$title</td>";
                $s = $s . "<td style='text-align:left;font-size:$font_size_table;' colspan='2'>$year</td>";
                $s = $s . "<td style='text-align:left;font-size:$font_size_summary;' colspan='2'>$summary</td>";
                $s = $s . "<td style='text-align:right;font-size:$font_size_table;' colspan='2'>$addedAt</td>";
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

            $rc = $this->CheckIpAdressPortStatus($ip, $port);
            if($rc['ErrorCode']==0) {
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
            } else {
                return $rc['ErrorCode'];
                IPS_LogMessage(IPS_GetName($_IPS['SELF'])." (". $_IPS['SELF'].")", "(".$rc['ErrorCode'].")"." ".$rc['ErrorMsg'] );
            }
        }

        // Mediathekname aus Integer Variable anhand des Namens ummappen und den Plex MediathekKey holen
        private function ReadMediaAssoziationMappedKey(string $value) 
        {
            // Formatted Wert aus Libraries ummappen zu Plex Mediathek
            $ip = $this->ReadPropertyString("IPAddress");
            $port = $this->ReadPropertyString("Port");

            $rc = $this->CheckIpAdressPortStatus($ip, $port);
            if($rc['ErrorCode']==0) {
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
            } else {
                return $rc['ErrorCode'];
                IPS_LogMessage(IPS_GetName($_IPS['SELF'])." (". $_IPS['SELF'].")", "(".$rc['ErrorCode'].")"." ".$rc['ErrorMsg'] );
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
    }