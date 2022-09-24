<?php
/**
 * Alternative solution for registering
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2017-2022 Denis Chenu <http://www.sondages.pro>
 * @copyright 2017 SICODA GmbH <http://www.sicoda.de>
 * @copyright 2017 www.marketaccess.ca <https://www.marketaccess.ca/>
 * @license AGPL v3
 * @version 1.3.0
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
class registerQuick extends PluginBase {

    protected $storage = 'DbStorage';

    static protected $description = 'Quick register system, replace default register system by a quickest way.';
    static protected $name = 'registerQuick';

    /** @inheritdoc , none here */
    public $allowedPublicMethods = [];

    public function init()
    {
        $this->subscribe('beforeActivate');

        $this->subscribe('beforeSurveySettings');
        $this->subscribe('newSurveySettings');

        $this->subscribe('beforeRegisterForm');
        $this->subscribe('beforeRegister');

        $this->subscribe('getValidScreenFiles');

    }

    /**
     * Activate or not
     */
    public function beforeActivate()
    {
        $lsVersion = floatval(Yii::app()->getConfig('versionnumber'));
        if (version_compare($lsVersion, "3.9.0", ">")) {
            return true;
        }
        if (version_compare($lsVersion, "3.0.0", "<")) {
            $this->getEvent()->set('message', gT("Only for LimeSurvey 3.0.0 and up version"));
            $this->getEvent()->set('success', false);
        }
        $oTwigExtendByPlugins = Plugin::model()->find("name=:name",array(":name"=>'twigExtendByPlugins'));
        if(!$oTwigExtendByPlugins) {
            $this->getEvent()->set('message', gT("You must download twigExtendByPlugins plugin"));
            $this->getEvent()->set('success', false);
        } elseif(!$oTwigExtendByPlugins->active) {
            $this->getEvent()->set('message', gT("You must activate twigExtendByPlugins plugin"));
            $this->getEvent()->set('success', false);
        }
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
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        $oEvent = $this->event;
        $oEvent->set("surveysettings.{$this->id}", array(
            'name' => get_class($this),
            'settings' => array(
                'quickRegistering'=>array(
                    'type'=>'boolean',
                    'label'=>$this->gT('Use quick registering'),
                    'current'=>$this->get('quickRegistering','Survey',$oEvent->get('survey'),0)
                ),
                'emailValidation'=>array(
                    'type'=>'select',
                    'label'=>$this->gT('Email settings'),
                    'options'=>array(
                            'show'=>$this->gT("Shown but allow empty"),
                            'hide'=>$this->gT("Hide and don't use it"),
                        ),
                    'htmlOptions'=>array(
                        'empty'=>$this->gT("Validate like LimeSurvey core (default)"),
                    ),
                    'current'=>$this->get('emailValidation','Survey',$oEvent->get('survey'),"")
                ),
                'emailMultiple' => array(
                    'type'=>'select',
                    'label'=>$this->gT('Existing Email'),
                    'options'=>array(
                        'getold' => $this->gT("Reload previous response."),
                        'renew'=> $this->gT("Create a new one if already completed"),
                    ),
                    'htmlOptions'=>array(
                        'empty'=>$this->gT("Create a new token each time."),
                    ),
                    'current'=>$this->get('emailMultiple','Survey',$oEvent->get('survey'),"")
                ),
                'emailSecurity'=>array(
                    'type'=>'boolean',
                    'label'=>$this->gT('Privacy of response'),
                    'help'=>$this->gT("If email exist : disable reloading survey without token. Warning: enabling reload of survey only with the e-mail address can cause privacy issue. The message will be sent if the email address exists."),
                    'current'=>$this->get('emailSecurity','Survey',$oEvent->get('survey'),1)
                ),
                'emailSend'=>array(
                    'type'=>'boolean',
                    'label'=>$this->gT('Send the email.'),
                    'help'=>$this->gT("If user put an email address, the register message will be sent."),
                    'current'=>$this->get('emailSend','Survey',$oEvent->get('survey'),0)
                ),
                'showTokenForm'=>array(
                    'type'=>'boolean',
                    'label'=>$this->gT('Show the token form.'),
                    'current'=>$this->get('showTokenForm','Survey',$oEvent->get('survey'),0)
                ),
            )
        ));
    }

    /**
    * @see newSurveySettings
    */
    public function newSurveySettings()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        $event = $this->event;
        foreach ($event->get('settings') as $name => $value) {
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
            
            /* Control survey access and Fix langage according to survey @see https://bugs.limesurvey.org/view.php?id=12641 */
            $oSurvey=Survey::model()->findByPK($iSurveyId);
            if (!$oSurvey) {
                throw new CHttpException(404, "The survey in which you are trying to participate does not seem to exist. It may have been deleted or the link you were given is outdated or incorrect.");
            } elseif($oSurvey->allowregister!='Y' || !tableExists("{{tokens_{$iSurveyId}}}")) {
                throw new CHttpException(404,"The survey in which you are trying to register don't accept registration. It may have been updated or the link you were given is outdated or incorrect.");
            } elseif(!is_null($oSurvey->expires) && $oSurvey->expires < dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i", Yii::app()->getConfig('timeadjust'))) {
                $this->redirect(array('survey/index','sid'=>$iSurveyId,'lang'=>$sLanguage));
            }
            $this->_fixLanguage($iSurveyId);
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
            $this->subscribe('getPluginTwigPath');
            $this->getEvent()->set('registerForm',$this->_getRegisterForm($iSurveyId));
        }
    }

    /**
     * Forced twig and new twig when register
     */
    public function getPluginTwigPath()
    {
        $viewPath = dirname(__FILE__)."/views";
        $forcedPath = dirname(__FILE__)."/forced";
        $this->getEvent()->append('TwigExtendOption', array($viewPath));
        $this->getEvent()->append('TwigExtendForced', array($forcedPath));
        $this->getEvent()->append('add', array($viewPath));
        $this->getEvent()->append('replace', array($forcedPath));
    }

    /**
     * For edition of twig
     */
    public function getValidScreenFiles()
    {
        if (!$this->getEvent()) {
            throw new CHttpException(403);
        }
        if(
            $this->getEvent()->get("type")!='view' ||
            ($this->getEvent()->get("screen") && $this->getEvent()->get("screen")!="register")
        ){
            return;
        }
        if($this->getEvent()->get("screen")) {
            $this->getEvent()->append('remove', array("subviews/registration/register_form.twig"));
        }
        $this->getEvent()->append('add', array("subviews/registration/registerquick_form.twig","subviews/registration/registerquick_token_form.twig"));
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
            if(empty($this->_aRegisterError)){
                if($this->get('emailSend','Survey',$iSurveyId)){
                    $this->_sendRegistrationEmail($iSurveyId,$iTokenId);
                }
                $this->_redirectToToken($iSurveyId,$iTokenId);
            }
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
        $this->unsubscribe('beforeRegisterForm');
        Yii::import('application.controllers.RegisterController');
        $RegisterController= new RegisterController('register');
        $this->_fixLanguage($iSurveyId);
        $aRegisterFormInfo = $RegisterController->getRegisterForm($iSurveyId); // Here come a array, not a form
        $emailValidation=$this->get('emailValidation','Survey',$iSurveyId,'');
        $aRegisterFormInfo['showEmail'] = (empty($emailValidation) || $emailValidation=='show');
        $aRegisterFormInfo['requiredEmail'] = ($emailValidation=='');
        $aRegisterFormInfo['showTokenForm'] = (bool) $this->get('showTokenForm','Survey',$iSurveyId,0);
        $aRegisterFormInfo['surveyUrl'] = App()->createUrl("/survey/index/",array('sid'=>$iSurveyId,'lang'=>App()->getLanguage()));;

        /* Complete by register errors */
        if(App()->getRequest()->getPost('register')) {
            $registerErrors = $this->_getRegisterErrors($iSurveyId);
            $aRegisterFormInfo['aErrors'] = $registerErrors;
        }
        $aRegisterFormInfo['options']['ajaxmode'] = "off";/* Seem to be replaced after, then adding a script in twig file */
        $aRegisterFormInfo['surveyls_title'] = sprintf($this->gT("Registering to : %s"),Survey::model()->findByPk($iSurveyId)->getLocalizedTitle());
        return $aRegisterFormInfo;
    }

    /**
     * Create the token and return it
     * @param integer iSurveyId
     * @return integer the token id
     */
    private function _getTokenId($iSurveyId){
        Yii::import('application.controllers.RegisterController');
        $RegisterController= new RegisterController('register');
        $this->_fixLanguage($iSurveyId);
        $aSurveyInfo=getSurveyInfo($iSurveyId,App()->getLanguage());
        $aFieldValue=$RegisterController->getFieldValue($iSurveyId);
        if($aFieldValue['sEmail']!="" && $this->get('emailMultiple','Survey',$iSurveyId)) {
            $oToken=Token::model($iSurveyId)->findByAttributes(array('email' => $aFieldValue['sEmail']));
            if($oToken) {
                if($oToken->usesleft<1 && $aSurveyInfo['alloweditaftercompletion']!='Y') {
                    switch (trim($this->get('emailMultiple','Survey',$iSurveyId)) ) {
                        case 'renew' :
                            $oToken=null;
                            break;
                        case 'getold' :
                        default :
                            $this->_aRegisterError[]=gT("The email address you have entered is already registered and the survey has been completed.");
                    }
                } elseif(strtolower(substr(trim($oToken->emailstatus),0,6))==="optout") {
                    $this->_aRegisterError[]=$this->gT("This email address cannot be used because it was opted out of this survey.");
                } elseif(!$oToken->emailstatus && $oToken->emailstatus!="OK") {
                    $this->_aRegisterError[]=$this->gT("This email address is already registered but the email adress was bounced.");
                } elseif($aSurveyInfo['alloweditaftercompletion']=='Y' || $oToken->usesleft > 0) {
                    if($this->get('emailSecurity','Survey',$iSurveyId,1)) {
                        $this->_aRegisterError[]=$this->gT("This email address is already registered, entering in survey is only allowed with token.");
                        $this->_sendRegistrationEmail($iSurveyId,$oToken->tid);
                    }
                }
            }
        }
        if(!empty($oToken)) {
            return $oToken->tid;
        }
        $oToken= Token::create($iSurveyId);
        $oToken->firstname = sanitize_xss_string($aFieldValue['sFirstName']);
        $oToken->lastname = sanitize_xss_string($aFieldValue['sLastName']);
        $oToken->email = $aFieldValue['sEmail'];
        $oToken->emailstatus = 'OK';
        $oToken->language = App()->getLanguage();
        $aFieldValue['aAttribute']=array_map('sanitize_xss_string',$aFieldValue['aAttribute']);
        $oToken->setAttributes($aFieldValue['aAttribute']);
        if ($aSurveyInfo['startdate']) {
            $oToken->validfrom = $aSurveyInfo['startdate'];
        }
        if ($aSurveyInfo['expires']) {
            $oToken->validuntil = $aSurveyInfo['expires'];
        }
        $oToken->generateToken();
        $oToken->save();
        return $oToken->tid;
    }

    /**
     * Send the registration email
     * @see RegisterController->sendRegistrationEmail();
     * @param integer $iSureyId
     * @param integer $iTokenId
     * @return boolean 
     */
    private function _sendRegistrationEmail($iSurveyId,$iTokenId){
        Yii::import('application.controllers.RegisterController');
        $RegisterController = new RegisterController('register');
        $this->_fixLanguage($iSurveyId);
        $done = $RegisterController->sendRegistrationEmail($iSurveyId,$iTokenId);
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
        if (function_exists("ImageCreate") && isCaptchaEnabled('registrationscreen',$aSurveyInfo['usecaptcha']) ) {
            $sLoadSecurity=Yii::app()->request->getPost('loadsecurity','');
            $captcha=Yii::app()->getController()->createAction("captcha");
            $captchaCorrect = $captcha->validate( $sLoadSecurity, false);

            if (!$captchaCorrect) {
                $aRegisterErrors[] = gT("Your answer to the security question was not correct - please try again.");
            }
        }
        Yii::import('application.controllers.RegisterController');
        $RegisterController= new RegisterController('register');
        $this->_fixLanguage($iSurveyId);
        $aFieldValue=$RegisterController->getFieldValue($iSurveyId);
        $aRegisterAttributes=$RegisterController->getExtraAttributeInfo($iSurveyId);

        //Check that the email is a valid style address
        if ($aFieldValue['sEmail']!="" && !validateEmailAddress($aFieldValue['sEmail'])) {
            $aRegisterErrors[]= gT("The email you used is not valid. Please try again.");
        } elseif ($aFieldValue['sEmail']=="" && $this->get('emailValidation','Survey',$iSurveyId,'')=="") {
            $aRegisterErrors[]= gT("You must enter a valid email. Please try again.");
        }
        //Check and validate attribute
        foreach ($aRegisterAttributes as $key => $aAttribute) {
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
        $redirectUrl = App()->createUrl("/survey/index/",array('sid'=>$iSurveyId,'lang'=>$sLanguage,'token'=>$sToken,'newtest'=>'Y'));
        Yii::app()->getController()->redirect($redirectUrl);
        Yii::app()->end();
    }

    /**
     * Log message
     * @return void
     */
    public function log($message, $level = \CLogger::LEVEL_TRACE)
    {
        if(is_callable("parent::log")) {
            parent::log($message, $level);
        }
        Yii::log("[".get_class($this)."] ".$message, $level, 'vardump');
    }

    /**
     * fix the language
     * @param $iSurveyId
     * @return void
     */
    private function _fixLanguage($iSurveyId)
    {
        $oSurvey = Survey::model()->findByPk($iSurveyId);
        $language = $oSurvey->language;
        $userLanguage = App()->getRequest()->getParam('lang',App()->getRequest()->getPost('lang'));
        if(in_array($userLanguage,$oSurvey->getAllLanguages()) ) {
            $language = $userLanguage;
        }
        App()->setLanguage($language);
    }
    
    /**
     * @inheritdoc
     * With default escape mode to 'unescaped'
     */
    public function gT($sToTranslate, $sEscapeMode = 'unescaped', $sLanguage = null)
    {
        return parent::gT($sToTranslate, $sEscapeMode, $sLanguage);
    }
}
