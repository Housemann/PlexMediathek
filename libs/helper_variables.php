<?php

trait PLEX_HelperVariables
{

    /**
     * RegisterProfileInteger (creating a integer variable profile with given parameters)
     *
     * @param $Name
     * @param $Icon
     * @param $Prefix
     * @param $Suffix
     * @param $MinValue
     * @param $MaxValue
     * @param $StepSize
     * @return bool
     */
    protected function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
    {
        if (IPS_VariableProfileExists($Name) === false) {
            IPS_CreateVariableProfile($Name, 1);
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] !== 1) {
                $this->SendDebug(__FUNCTION__, 'Type of variable does not match the variable profile "' . $Name . '"', 0);
                return false;
            }
        }

        if ($StepSize > 0) {
            IPS_SetVariableProfileDigits($Name, 1);
        }

        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);

        return true;
    }


    /**
     * RegisterProfileIntegerEx (creating a integer variable profile with given parameters and extra associations)
     *
     * @param $Name
     * @param $Icon
     * @param $Prefix
     * @param $Suffix
     * @param $Associations
     * @return bool
     */
    protected function RegisterProfileIntegerEx($Name, $Icon, $Prefix, $Suffix, $Associations)
    {
        if (count($Associations) === 0) {
            $MinValue = 0;
            $MaxValue = 0;
        } else {
            $MinValue = $Associations[0][0];
            $MaxValue = $Associations[count($Associations) - 1][0];
        }

        $this->RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);

        foreach ($Associations as $Association) {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
        }

        return true;
    }
 

    /**
     * Variable_Register (register and create variable with some parameters)
     *
     * @param $VarIdent
     * @param $VarName
     * @param $VarProfile
     * @param $VarIcon
     * @param $VarType
     * @param $EnableAction
     * @param $PositionX
     */
    protected function Variable_Register($VarIdent, $VarName, $VarProfile, $VarIcon, $VarType, $EnableAction, $PositionX = false, $Debug = false)
    {
      if ($PositionX === false) {
          $Position = 0;
      } else {
          $Position = $PositionX;
      }

      switch ($VarType) {
          case 0:
            $this->RegisterVariableBoolean($VarIdent, $VarName, $VarProfile, $Position);
            $Debug_VarType = "bool";
            break;

          case 1:
            $this->RegisterVariableInteger($VarIdent, $VarName, $VarProfile, $Position);
            $Debug_VarType = "integer";
            break;

          case 2:
            $this->RegisterVariableFloat($VarIdent, $VarName, $VarProfile, $Position);
            $Debug_VarType = "float";
            break;

          case 3:
            $this->RegisterVariableString($VarIdent, $VarName, $VarProfile, $Position);
            $Debug_VarType = "string";
            break;
      }

      if ($VarIcon !== '') {
          IPS_SetIcon($this->GetIDForIdent($VarIdent), $VarIcon);
      }

      if ($EnableAction === true) {
          $this->EnableAction($VarIdent);
      }

      if ($Debug === true) {
        $Debug_Msg = "Create Variable with Type=".$Debug_VarType." (".$this->GetIDForIdent($VarIdent)."), EnableAction="."$EnableAction".",Icon=\""."$VarIcon"."\",Position="."$Position".".";
        $this->SendDebug("Variable_Register", $Debug_Msg, 0);
      }
    }

    /**
     * CheckIpAdressPortStatus (chekc IP and Port is Valid and Connects via fsockopen)
     *
     * @param $ip
     * @param $port
     */
    protected function CheckIpAdressPortStatus($ip, $port) {

        $errorCode = -50000;
        $rc=0;
        $errmsg="No error";
      
        // IP Check
        if(is_numeric(str_replace('.','',$ip))==true) {
          if(!filter_var($ip, FILTER_VALIDATE_IP)) {
            $rc=-10;
            $errmsg="IP is not valid";
          }
        } else {
          if(!filter_var($ip, FILTER_VALIDATE_URL)) {
            $rc=-12;
            $errmsg="URL is not valid";
          } else {
            $rc=12;
            $errmsg="URL is not valid";
          }
        }
      
        if($rc==0 && !is_numeric($port)) {
          $rc=-22;
          $errmsg="Port is not numeric";
        }
        if($rc==0 && strlen($port)>5) {
          $rc=-24;
          $errmsg="Port has more than 5 sigits";
        }
        if($rc==0 && intval($port)>65535) {
          $rc=-26;
          $errmsg="Port is higher then the Number 65535";
        }
        
        if($rc==0) {
          $fso = @fsockopen($ip, $port, $errno, $errstr, 2);
          @socket_set_timeout($fso, 0, 10000);
          if($errno<>0) {
            $rc=-$errno;
            $errmsg='fsockopen: '.$errstr;
          }
        }
        
        if($rc<0)
          $rc=$errorCode+$rc;
      
        return array("ErrorCode" => $rc,
                     "ErrorMsg"  => $errmsg);
      }    

}
