<?php
    
    require_once __DIR__ . '/../libs/helper_variables.php';

    // Klassendefinition
    class PlexMediathek extends IPSModule {

        use PLEX_HelperVariables;
 
        // Überschreibt die interne IPS_Create($id) Funktion
        public function Create() {
            // Diese Zeile nicht löschen.
            parent::Create();

            // Propertys anlegen
            $this->RegisterPropertyString ("IPAddress", "2.2.2.2");
            $this->RegisterPropertyString ("Port", "32400");

            $this->RegisterPropertyInteger ("UpdateIntervall", 60);
            
            $this->RegisterPropertyInteger ("ColorHeader", -1);
            $this->RegisterPropertyInteger ("FontColorHeader", -1);
            $this->RegisterPropertyInteger ("FontSizeHeader", 16);
            $this->RegisterPropertyInteger ("ColorTable", -1);
            $this->RegisterPropertyInteger ("FontSizeTable", 14);
            $this->RegisterPropertyInteger ("FontColorTable", -1);

            $this->RegisterPropertyInteger ("BoarderColor", -1);
            $this->RegisterPropertyString ("BorderStyle", "outset");
            $this->RegisterPropertyInteger ("BorderWidth", 1);

            
            $this->RegisterPropertyString ("QuantityPerPage","100");

            // Helper Buffer
            $this->SetBuffer("CurrentSite","");
            $this->SetBuffer("MaxSite","");
 
            // Timer anlegen
            $this->RegisterTimer ("UpdateMediathek", 0, 'PLEX_ReadAndFillHtmlFromPlex($_IPS[\'TARGET\'],\'Libraries\');');
        }

        public function Destroy() 
        {
            // Remove variable profiles from this module if there is no instance left
            $InstancesAR = IPS_GetInstanceListByModuleID('{41434F5C-B8DD-ECFA-8591-9E2F2C553FC4}');
            if ((@array_key_exists('0', $InstancesAR) === false) || (@array_key_exists('0', $InstancesAR) === NULL)) {
                $VarProfileAR = array('PLEX.SiteCount','PLEX.Mediatheken');
                foreach ($VarProfileAR as $VarProfileName) {
                    @IPS_DeleteVariableProfile($VarProfileName);
                }
            }
            parent::Destroy();
        }       
 
        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() {
            // Diese Zeile nicht löschen
            parent::ApplyChanges();

            // Profil Mediatheken anlegen
            $this->RegisterProfileInteger("PLEX.Mediatheken", "", "", "", 0, 0, 0);
            
            // Variablen anlegen
            $this->Variable_Register("Libraries", $this->translate("Libraries"), "PLEX.Mediatheken", "", 1, true,1 ,true);
            $this->Variable_Register("MediaHTMLBox", $this->translate("Media"), "~HTMLBox", "", 3, "", 3);

            // VariablenProfil und Seitenwechsler anlegen
            $this->RegisterProfileIntegerEx('PLEX.SiteCount', '', '', '', Array(
                Array(1 , '  <<  ', '', -1),   			           
                Array(2 , '  <  ' , '', -1),
                Array(3 , '  0  ' , '', -1),
                Array(4 , '  >  ' , '', -1),
                Array(5 , '  >>  ', '', -1)
            ));
            $this->Variable_Register("Site", $this->translate("Site"), "PLEX.SiteCount", "", 1, true, 2, true);


            // Timer zum Mediathek Update
            $this->SetTimerInterval("UpdateMediathek", $this->ReadPropertyInteger("UpdateIntervall") * 60 * 1000);

            // HTML Box neu laden
            if(IPS_VariableExists(@$this->GetIDForIdent("Libraries"))) {
                $CurSite = $this->SetBuffer("CurrentSite",1);
                $this->ReadAndFillHtmlFromPlex ("Libraries", 1, true);
                $MaxSite = $this->GetBuffer("MaxSite");
                IPS_SetVariableProfileAssociation ("PLEX.SiteCount", 3, "$CurSite / $MaxSite", "", -1);
            }
        }
 

        public function RequestAction($Ident, $Value) 
        {
            switch($Ident) {
                // Wenn Bibliotheken ausgewählt werden
                case "Libraries":
                    SetValue($this->GetIDForIdent($Ident), $Value);                   
                    
                    $CurSite = $this->SetBuffer("CurrentSite",1);

                    //HTML Box mit Inhalt neu fuellen
                    $this->ReadAndFillHtmlFromPlex ($Ident, $CurSite, true);

                    $MaxSite = $this->GetBuffer("MaxSite");
                    IPS_SetVariableProfileAssociation ("PLEX.SiteCount", 3, "$CurSite / $MaxSite", "", -1);

                break;
                // Wenn Seitenwechler gedrückt wird
                case "Site":
                    $CurSite = $this->GetBuffer("CurrentSite");
                    $MaxSite = $this->GetBuffer("MaxSite");

                    if($Value==1) {
                        // Aus erste Seite springen
                        SetValue($this->GetIDForIdent($Ident),$Value);
                        
                        $Site=1;
                    } elseif($Value==2) {
                        // Seite zurück
                        SetValue($this->GetIDForIdent($Ident),$Value);
                     
                        $Site=$CurSite-1;

                        // Damit man nicht auf die Seite -1 gelangt
                        if($Site<1) {
                            $Site=1;
                        }
                    } elseif($Value==4) {
                        // Seite vor
                        SetValue($this->GetIDForIdent($Ident),$Value);
                        
                        $Site=$CurSite+1;

                        // Damit man nicht auf die Seite groeßer der max Seite gelangt
                        if($Site>$MaxSite) {
                            $Site=$MaxSite;
                        } 
                    } elseif($Value==5) {
                        // Auf letzte Seite springen
                        SetValue($this->GetIDForIdent($Ident),$Value);
                        
                        $Site=$MaxSite;
                    }
                    
                    // Buffer mit aktueller Seite setzen und merken
                    $this->SetBuffer("CurrentSite",$Site);

                    // Variablenprofil Wert 3 Benennung AktuelleSeite / MaxSeite
                    IPS_SetVariableProfileAssociation ("PLEX.SiteCount", 3, "$Site / $MaxSite", "", -1);

                    //HTML Box mit Inhalt neu fuellen
                    $this->ReadAndFillHtmlFromPlex ("Libraries", $Site, false);
                
                break;
                default:
                    throw new Exception("Invalid Ident");
            }
        }

        // Grundfunktion die Daten zusammenstellt
        public function ReadAndFillHtmlFromPlex (string $Ident, int $SiteNumber, int $CMA) 
        {
            $ip = $this->ReadPropertyString("IPAddress");
            $port = $this->ReadPropertyString("Port");

            $rc = $this->CheckIpAdressPortStatus($ip, $port);
            if($rc['ErrorCode']==0) {

                // Profil assoziationen NUR aktualisieren wenn Bibliothek gewechselt wird, nicht bei seiten blaettern
                if($CMA==true) {
                    $this->CreateMediaAssoziations();
                }
                
                // VarId zu Ident holen
                $FormattedValueMediathek = GetValueFormatted($this->GetIDForIdent($Ident));

                // Plex Daten für Mediathek auslesen
                $ret_array = $this->GetPlexMetaData ($FormattedValueMediathek, $SiteNumber);
                
                // HTML Box füllen
                $this->FillHtmlBox("MediaHTMLBox", $ret_array);
            
            } else {
                return $rc['ErrorCode'];
                IPS_LogMessage(IPS_GetName($_IPS['SELF'])." (". $_IPS['SELF'].")", "(".$rc['ErrorCode'].")"." ".$rc['ErrorMsg'] );
            }
        }


        // Metadaten für Mediathek auslesen und in arra schreiben
        private function GetPlexMetaData (string $FormattedValueMediathek, int $site) 
        {
            $ip = $this->ReadPropertyString("IPAddress");
            $port = $this->ReadPropertyString("Port");
            
            // Zum Formatted Ident z.B. BluRay den Plex Mediakey holen weil in Array vom Ident falsch ist
            $arrayMappedKey = $this->ReadMediaAssoziationMappedKey($FormattedValueMediathek); // return array 'key' 'title' 'type'

            // Mediathek über Plex Mediathek ID auslesen aus Auswahl
            $arrayMediathek = $this->ReadXmlFileMedia($arrayMappedKey[0]['key']);

            // Anzahl pro Seite holen
            $MaxCntSite = $this->ReadPropertyString("QuantityPerPage");

            // Seitenanzahl errechnen 
            $MediaCnt = $arrayMediathek['@attributes']['size'];
            $SiteCnt = intval(ceil($MediaCnt/intval($MaxCntSite)));

            // Buffer setzen mit Anzahl Medien aus dem Array
            $this->SetBuffer ("MaxSite", $SiteCnt);
            

            // Meidathek durchgehen und Anzahl der Medien zaehlen um verschiene Array zu fuellen 
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

              // buffer auslesen auf welcher Seite man sich befindet
              $Cursite = $this->GetBuffer("CurrentSite");

              // Berechnung der Werte die angezeigt werden sollen
              $ValueFrom = ($Cursite * intval($MaxCntSite)) - intval($MaxCntSite);
              $ValueTo   = $ValueFrom + intval($MaxCntSite);

              // Array abhaengig von Anzahl fuellen (weil PLEX [$i] bei einem Wert weg laesst)
              $array=array();
              if($count_all_sections>1) {
                  for($i = $ValueFrom; $i < $ValueTo; $i++) {
                      if(!empty(@$arrayMediathek[$SetValueArray][$i]['@attributes']['title'])) {
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

            $type = @$array[0]['type'];

            // Propertys lesen und Farbe umwandeln in HEX
            $color_header       = str_replace("0x","#",$this->IntToHex($this->ReadPropertyInteger ("ColorHeader")));
            $font_size_header 	= $this->ReadPropertyInteger ("FontSizeHeader")."px";
            $font_color_header  = str_replace("0x","#",$this->IntToHex($this->ReadPropertyInteger ("FontColorHeader")));

            $color_table        = str_replace("0x","#",$this->IntToHex($this->ReadPropertyInteger ("ColorTable")));
            $font_size_table 	= $this->ReadPropertyInteger ("FontSizeTable")."px";
            $font_color_table   = str_replace("0x","#",$this->IntToHex($this->ReadPropertyInteger ("FontColorTable")));

            $border_style       = $this->ReadPropertyString ("BorderStyle"); // dotted,dashed,solid,double,groove,ridge,inset,outset,none,hidden
            $border_width       = $this->ReadPropertyInteger ("BorderWidth")."px";
            $boarder_color      = str_replace("0x","#",$this->IntToHex($this->ReadPropertyInteger ("BoarderColor")));

            // HTML Tabelle aufbauen
            $s = '';
            $s = $s . "<style type='text/css'>";
            $s = $s . "table.test { width: 100%; border-collapse: true;}";
            $s = $s . "CSS { border: 1px solid #444455; border-style: $border_style; border-width: $border_width;}</style>";
            $s = $s . "<table class='CSS'>";

            $s = $s . "<tr>";
            $s = $s . "<th style='border-color: $boarder_color; color: $font_color_header; border-width: $border_width; border-style: $border_style; text-align:cwnter;background: $color_header;font-size:$font_size_header;' colspan='2'><B>Cover</td>";

            if($type === "artist") {
                $s = $s . "<th style='border-color: $boarder_color; color: $font_color_header; border-width: $border_width; border-style: $border_style; width: 20px;text-align:left;background: $color_header;font-size:$font_size_header;' colspan='2'><B>".$this->translate("Artist")."</td>";
            } elseif($type === "show") {
                $s = $s . "<th style='border-color: $boarder_color; color: $font_color_header; border-width: $border_width; border-style: $border_style; width: 20px;text-align:left;background: $color_header;font-size:$font_size_header;' colspan='2'><B>".$this->translate("Series")."</td>";
            } elseif($type === "movie") {
                $s = $s . "<th style='border-color: $boarder_color; color: $font_color_header; border-width: $border_width; border-style: $border_style; width: 20%;text-align:left;background: $color_header;font-size:$font_size_header;' colspan='2'><B>".$this->translate("Movie")."</td>";
            } elseif($type === "photo") {
                $s = $s . "<th style='border-color: $boarder_color; color: $font_color_header; border-width: $border_width; border-style: $border_style; width: 20%;text-align:left;background: $color_header;font-size:$font_size_header;' colspan='2'><B>".$this->translate("Photoalbum")."</td>";
            }

            $s = $s . "<th style='border-color: $boarder_color; color: $font_color_header; border-width: $border_width; border-style: $border_style; text-align:center;background: $color_header;font-size:$font_size_header;'width=150px; colspan='2'><B>".$this->translate("Year")."</td>";

            // Wenn Photo Spalte beschreibung weglassen
            if($type !== "photo") {
                $s = $s . "<th style='border-color: $boarder_color; color: $font_color_header; border-width: $border_width; border-style: $border_style; background: $color_header; font-size:$font_size_header;' colspan='2'><B>".$this->translate("Summery")."</td>";

            }

            $s = $s . "<th style='border-color: $boarder_color; color: $font_color_header; border-width: $border_width; border-style: $border_style; text-align:center;background: $color_header;font-size:$font_size_header;'width=250px; colspan='2'><B>".$this->translate("Added")."</td>";
            
            $s = $s . "</tr>";
            $s = $s . "<tr>";

            foreach($array as $key) {
                $type = $key['type'];

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
                $s = $s . "<td style='border-color: $boarder_color; color: $font_color_table; border-width: $border_width; border-style: $border_style; text-align:center; background: $color_table; font-size:$font_size_table;' colspan='2'>$pic</td>";
                $s = $s . "<td style='border-color: $boarder_color; color: $font_color_table; border-width: $border_width; border-style: $border_style; background: $color_table; font-size:$font_size_table;' colspan='2'>$title</td>";
                $s = $s . "<td style='border-color: $boarder_color; color: $font_color_table; border-width: $border_width; border-style: $border_style; text-align:center; background: $color_table;font-size:$font_size_table;' colspan='2'>$year</td>";

                // Wenn Photo Spalte beschreibung weglassen
                if($type !== "photo") {
                    $s = $s . "<td style='border-color: $boarder_color; color: $font_color_table; border-width: $border_width; border-style: $border_style; text-align:left; background: $color_table; font-size:$font_size_table;' colspan='2'>$summary</td>";
                }

                $s = $s . "<td style='border-color: $boarder_color; color: $font_color_table; border-width: $border_width; border-style: $border_style; text-align:center; background: $color_table; font-size:$font_size_table;' colspan='2'>$addedAt</td>";
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

            // Mediatheken auslesen
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

            // Mediatheken auslesen
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