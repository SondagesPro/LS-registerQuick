<?php
/**
 * Plugin helper for limesurvey : quick render a message to public user
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2017 Denis Chenu <http://www.sondages.pro>
 * @copyright 2017 SICODA GmbH <http://www.sicoda.de>
 * @license AGPL v3
 * @version 0.0.1
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
class registerQuick extends \ls\pluginmanager\PluginBase {

    protected $storage = 'DbStorage';

    static protected $description = 'Quick register system, replace default register system by a quickest way.';
    static protected $name = 'registerQuick';

    public function init()
    {
        $this->subscribe('beforeSurveySettings');
        $this->subscribe('newSurveySettings');

        $this->subscribe('beforeRegisterForm');
        $this->subscribe('beforeRegister');

    }

    /**
     * var string[] The registered erros for this plugin
     */
    private $_aRegisterError=array();

    /**
    * @see beforeSurveySettings
    */
    public function beforeSurveySettings()
    {
        $oEvent = $this->event;
        $oEvent->set("surveysettings.{$this->id}", array(
            'name' => get_class($this),
            'settings' => array(
                'quickRegistering'=>array(
                    'type'=>'boolean',
                    'label'=>$this->_translate('Use quick registering'),
                    'current'=>$this->get('quickRegistering','Survey',$oEvent->get('survey'),0)
                ),
                'emailValidation'=>array(
                    'type'=>'select',
                    'label'=>$this->_translate('Email settings'),
                    'options'=>array(
                            'show'=>$this->_translate("Shown but allow empty"),
                            'hide'=>$this->_translate("Hide and don't use it"),
                        ),
                    'htmlOptions'=>array(
                        'empty'=>$this->_translate("Validate like LimeSurvey core (default)"),
                    ),
                    'current'=>$this->get('emailValidation','Survey',$oEvent->get('survey'),"")
                ),
                'emailSend'=>array(
                    'type'=>'boolean',
                    'label'=>$this->_translate('Send the email (if exist).'),
                    'current'=>$this->get('emailSend','Survey',$oEvent->get('survey'),0)
                ),
            )
        ));
    }

    /**
    * @see newSurveySettings
    */
    public function newSurveySettings()
    {
        $event = $this->event;
        foreach ($event->get('settings') as $name => $value)
        {
            /* In order use survey setting, if not set, use global, if not set use default */
            //~ $default=$event->get($name,null,null,isset($this->settings[$name]['default'])?$this->settings[$name]['default']:NULL);
            $this->set($name, $value, 'Survey', $event->get('survey'));
        }
    }

    /**
     * @see beforeRegister
     */
    public function beforeRegister()
    {
        $iSurveyId=$this->getEvent()->get('surveyid');
        if($this->get('quickRegistering','Survey',$iSurveyId)){
            if((Yii::app()->request->getPost('register'))){
                $this->_validateForm($iSurveyId);
            }
        }
    }
    /**
    * @see beforeRegisterForm
    */
    public function beforeRegisterForm()
    {
        $iSurveyId=$this->getEvent()->get('surveyid');

        if($this->get('quickRegistering','Survey',$iSurveyId)){
            $this->getEvent()->set('registerForm',$this->_getRegisterForm($iSurveyId));
        }
    }

    /**
     * Validate the register form and do action if needed
     * @param integer $iSurveyId
     * @return void
     */
    private function _validateForm($iSurveyId)
    {

        $this->_aRegisterError=$this->_getRegisterErrors($iSurveyId);
        if(empty($this->_aRegisterError)){
            $iTokenId=$this->_getTokenId($iSurveyId);
            if($this->get('emailSend','Survey',$iSurveyId)){
                $this->_sendRegistrationEmail($iSurveyId,$iTokenId);
            }
            $this->_redirectToToken($iSurveyId,$iTokenId);
        }
    }

    /**
     * Construct the new form with option
     * @see RegisterController->getRegisterForm()
     * @param integer $iSurveyId
     * @return string
     */
    private function _getRegisterForm($iSurveyId)
    {
        Yii::import('application.controllers.RegisterController');
        $RegisterController= new RegisterController('register');
        $sLanguage=App()->language;
        $aSurveyInfo=getSurveyInfo($iSurveyId,$sLanguage);
        $aFieldValue=$RegisterController->getFieldValue($iSurveyId);
        $aRegisterAttributes=$RegisterController->getExtraAttributeInfo($iSurveyId);

        $aData['iSurveyId'] = $iSurveyId;
        $aData['sLanguage'] = App()->language;
        $aData['sFirstName'] = $aFieldValue['sFirstName'];
        $aData['sLastName'] = $aFieldValue['sLastName'];
        $aData['sEmail'] = $aFieldValue['sEmail'];
        $aData['aAttribute'] = $aFieldValue['aAttribute'];
        $aData['aExtraAttributes']=$aRegisterAttributes;
        $aData['urlAction']=App()->createUrl('register/index',array('sid'=>$iSurveyId));
        $aData['bCaptcha'] = function_exists("ImageCreate") && isCaptchaEnabled('registrationscreen', $aSurveyInfo['usecaptcha']);
        /* Show hide email part */
        $emailValidation=$this->get('emailValidation','Survey',$iSurveyId,'');
        $aData['showEmail']=(empty($emailValidation) || $emailValidation=='show');
        $aData['requiredEmail']=($emailValidation=='');
        if(!empty($this->_aRegisterError))
        {
            $sRegisterError="<div class='alert alert-danger' role='alert'>"
            .implode('<br />',$this->_aRegisterError)
            ."</div>";
        }
        else
        {
            $sRegisterError='';
        }

        $aReplacement['REGISTERERROR'] = $sRegisterError;
        $aReplacement['REGISTERMESSAGE1'] = gT("You must be registered to complete this survey");
        if($sStartDate=$RegisterController->getStartDate($iSurveyId))
            $aReplacement['REGISTERMESSAGE2'] = sprintf(gT("You may register for this survey but you have to wait for the %s before starting the survey."),$sStartDate)."<br />\n".gT("Enter your details below, and an email containing the link to participate in this survey will be sent immediately.");
        else
            $aReplacement['REGISTERMESSAGE2'] = gT("You may register for this survey if you wish to take part.")."<br />\n".gT("Enter your details below, and an email containing the link to participate in this survey will be sent immediately.");


        //~ Yii::setPathOfAlias('registerQuick', dirname(__FILE__));
        $aReplacement['REGISTERFORM']=$this->renderPartial('registerForm',$aData,true);

        //~ $aReplacement['REGISTERFORM']=Yii::app()->getController()->renderPartial('registerQuick.views.registerForm',$aData,true);
        $aData['thissurvey'] = $aSurveyInfo;
        Yii::app()->setConfig('surveyID',$iSurveyId);//Needed for languagechanger
        $aData['languagechanger'] = makeLanguageChangerSurvey(App()->language);
        $oTemplate = Template::model()->getInstance(null, $iSurveyId);
        return templatereplace(file_get_contents($oTemplate->viewPath . "/register.pstpl"),$aReplacement,$aData);

    }

    /**
     * Create the token and return it
     * @param integer iSurveyId
     * @return integer the token id
     */
    private function _getTokenId($iSurveyId){
        Yii::import('application.controllers.RegisterController');
        $RegisterController= new RegisterController('register');
        $sLanguage=App()->language;
        $aSurveyInfo=getSurveyInfo($iSurveyId,$sLanguage);
        $aFieldValue=$RegisterController->getFieldValue($iSurveyId);
        $oToken= Token::create($iSurveyId);
        $oToken->firstname = sanitize_xss_string($aFieldValue['sFirstName']);
        $oToken->lastname = sanitize_xss_string($aFieldValue['sLastName']);
        $oToken->email = $aFieldValue['sEmail'];
        $oToken->emailstatus = 'OK';
        $oToken->language = $sLanguage;
        $aFieldValue['aAttribute']=array_map('sanitize_xss_string',$aFieldValue['aAttribute']);
        $oToken->setAttributes($aFieldValue['aAttribute']);
        if ($aSurveyInfo['startdate'])
        {
            $oToken->validfrom = $aSurveyInfo['startdate'];
        }
        if ($aSurveyInfo['expires'])
        {
            $oToken->validuntil = $aSurveyInfo['expires'];
        }
        $oToken->generateToken();
        $oToken->save();
        return $oToken->tid;
    }

    /**
     * Send the registration email
     * @see RegisterController->sendRegistrationEmail();
     */
    private function _sendRegistrationEmail($iSurveyId,$iTokenId){
        Yii::import('application.controllers.RegisterController');
        $RegisterController= new RegisterController('register');
        $done =$RegisterController->sendRegistrationEmail($iSurveyId,$iTokenId);
        return $done;
    }
    /**
    * Validate a register form
    * Because we need validating before default action happen
    * @param $iSurveyId Survey Id to register
    * @return array of errors when try to register (empty array => no error)
    */
    private function _getRegisterErrors($iSurveyId){
        $aSurveyInfo=getSurveyInfo($iSurveyId,App()->language);
        $aRegisterErrors=array();

        // Check the security question's answer
        if (function_exists("ImageCreate") && isCaptchaEnabled('registrationscreen',$aSurveyInfo['usecaptcha']) )
        {
            $sLoadSecurity=Yii::app()->request->getPost('loadsecurity','');
            $captcha=Yii::app()->getController()->createAction("captcha");
            $captchaCorrect = $captcha->validate( $sLoadSecurity, false);

            if (!$captchaCorrect)
            {
                $aRegisterErrors[] = gT("Your answer to the security question was not correct - please try again.");
            }
        }
        Yii::import('application.controllers.RegisterController');
        $RegisterController= new RegisterController('register');
        $aFieldValue=$RegisterController->getFieldValue($iSurveyId);
        $aRegisterAttributes=$RegisterController->getExtraAttributeInfo($iSurveyId);

        //Check that the email is a valid style address
        if ($aFieldValue['sEmail']!="" && !validateEmailAddress($aFieldValue['sEmail'])) {
            $aRegisterErrors[]= gT("The email you used is not valid. Please try again.");
        } elseif ($aFieldValue['sEmail']=="" && $this->get('emailValidation','Survey',$iSurveyId,'')=="") {
            $aRegisterErrors[]= gT("You must enter a valid email. Please try again.");
        }
        //Check and validate attribute
        foreach ($aRegisterAttributes as $key => $aAttribute)
        {
            if ($aAttribute['show_register'] == 'Y' && $aAttribute['mandatory'] == 'Y' && empty($aFieldValue['aAttribute'][$key]))
            {
                $aRegisterErrors[]= sprintf(gT("%s cannot be left empty").".", $aAttribute['caption']);
            }
        }
        return $aRegisterErrors;
    }
    /**
     * redirect to survey with the new token
     * @param integer $iSurveyId
     * @param integer $iTokenId
     * @return void (but redirect)
     */
    private function _redirectToToken($iSurveyId,$iTokenId){
        $oToken = Token::model($iSurveyId)->findByPk($iTokenId);
        $sToken=$oToken->token;
        $sLanguage=App()->language;
        $redirectUrl=App()->createUrl("/survey/index/",array('sid'=>$iSurveyId,'lang'=>$sLanguage,'token'=>$sToken,'newtest'=>'Y'));
        Yii::app()->getRequest()->redirect($redirectUrl);
    }
    /**
     * Translate a internal string
     * @param string $string
     * @return string
     */
    private function _translate($string){
        return Yii::t('',$string,array(),'registerQuick');
    }
    /**
     * Add this translation just after loaded all plugins
     * @see event afterPluginLoad
     */
    public function afterPluginLoad(){
        // messageSource for this plugin:
        $registerQuickMode=array(
            'class' => 'CGettextMessageSource',
            'cacheID' => 'registerQuickLang',
            'cachingDuration'=>3600,
            'forceTranslation' => true,
            'useMoFile' => true,
            'basePath' => __DIR__ . DIRECTORY_SEPARATOR.'locale',
            'catalog'=>'messages',// default from Yii
        );
        Yii::app()->setComponent('registerQuick',$registerQuickMode);
    }
}
