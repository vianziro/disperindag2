<?php
class AdminController extends CController
{
    public $layout='//layouts/column1';
    //public $layout='application.views.admin.layouts.column1';
    public $menu = array();
    public $breadcrumbs = array();

    /**
     * @var int
     * @desc items on page
     */
    public $user_page_size = 10;

    /**
     * @var int
     * @desc items on page
     */
    public $fields_page_size = 10;

    /**
     * @var string
     * @desc hash method (md5,sha1 or algo hash function http://www.php.net/manual/en/function.hash.php)
     */
    public $hash='md5';

    /**
     * @var boolean
     * @desc use email for activation user account
     */
    public $sendActivationMail=true;

    /**
     * @var boolean
     * @desc allow auth for is not active user
     */
    public $loginNotActiv=false;

    /**
     * @var boolean
     * @desc activate user on registration (only $sendActivationMail = false)
     */
    public $activeAfterRegister=false;

    /**
     * @var boolean
     * @desc login after registration (need loginNotActiv or activeAfterRegister = true)
     */
    public $autoLogin=true;

    public $registrationUrl = array("/admin/user/register");
    public $recoveryUrl = array("/admin/user/recovery");
    public $loginUrl = array("/admin/user/login");
    public $logoutUrl = array("/admin/user/logout");
    public $profileUrl = array("/admin/user/profile");
    public $returnUrl = array("/admin/user/profile");
    public $returnLogoutUrl = array("/admin/user/login");


    /**
     * @var int
     * @desc Remember Me Time (seconds), defalt = 2592000 (30 days)
     */
    public $rememberMeTime = 2592000; // 30 days

    public $fieldsMessage = '';

    /**
     * @var array
     * @desc User model relation from other models
     * @see http://www.yiiframework.com/doc/guide/database.arr
     */
    public $relations = array();

    /**
     * @var array
     * @desc Profile model relation from other models
     */
    public $profileRelations = array();

    /**
     * @var boolean
     */
    public $captcha = array('registration'=>true);

    /**
     * @var boolean
     */
    //public $cacheEnable = false;

    public $tableUsers = '{{users}}';
    public $tableProfiles = '{{profiles}}';
    public $tableProfileFields = '{{profiles_fields}}';

    public $defaultScope = array(
        'with'=>array('profile'),
    );

    static private $_user;
    static private $_users=array();
    static private $_userByName=array();
    static private $_admin;
    static private $_admins;

    /**
     * @var array
     * @desc Behaviors for models
     */
    public $componentBehaviors=array();

    public function getBehaviorsFor($componentName){
        if (isset($this->componentBehaviors[$componentName])) {
            return $this->componentBehaviors[$componentName];
        } else {
            return array();
        }
    }

    /**
     * @param $str
     * @param $params
     * @param $dic
     * @return string
     */
    public static function t($str='',$params=array(),$dic='admin') {
        if (Yii::t("AdminModule", $str)==$str)
            return Yii::t("AdminModule.".$dic, $str, $params);
        else
            return Yii::t("AdminModule", $str, $params);
    }

    /**
     * @return hash string.
     */
    public function encrypting($string="") {
        $hash = $this->hash;
        if ($hash=="md5")
            return md5($string);
        if ($hash=="sha1")
            return sha1($string);
        else
            return hash($hash,$string);
    }

    /**
     * @param $place
     * @return boolean
     */
    public function doCaptcha($place = '') {
        if(!extension_loaded('gd'))
            return false;
        if (in_array($place, $this->captcha))
            return $this->captcha[$place];
        return false;
    }

    /**
     * Return admin status.
     * @return boolean
     */
    public static function isAdmin() {
        if(Yii::app()->user->isGuest)
            return false;
        else {
            if (!isset(self::$_admin)) {
                if(self::user()->superuser)
                    self::$_admin = true;
                else
                    self::$_admin = false;
            }
            return self::$_admin;
        }
    }

    /**
     * Return admins.
     * @return array syperusers names
     */
    public static function getAdmins() {
        if (!self::$_admins) {
            $admins = Users::model()->active()->superuser()->findAll();
            $return_name = array();
            foreach ($admins as $admin)
                array_push($return_name,$admin->username);
            self::$_admins = ($return_name)?$return_name:array('');
        }
        return self::$_admins;
    }

    /**
     * Send mail method
     */
    public static function sendMail($email,$subject,$message) {
        $adminEmail = Yii::app()->params['adminEmail'];
        $headers = "MIME-Version: 1.0\r\nFrom: $adminEmail\r\nReply-To: $adminEmail\r\nContent-Type: text/html; charset=utf-8";
        $message = wordwrap($message, 70);
        $message = str_replace("\n.", "\n..", $message);
        return mail($email,'=?UTF-8?B?'.base64_encode($subject).'?=',$message,$headers);
    }

    /**
     * Return safe user data.
     * @param user id not required
     * @return user object or false
     */
    public static function user($id=0,$clearCache=false) {
        if (!$id&&!Yii::app()->user->isGuest)
            $id = Yii::app()->user->id;
        if ($id) {
            if (!isset(self::$_users[$id])||$clearCache)
                self::$_users[$id] = Users::model()->with(array('profile'))->findbyPk($id);
            return self::$_users[$id];
        } else return false;
    }

    /**
     * Return safe user data.
     * @param user name
     * @return user object or false
     */
    public static function getUserByName($username) {
        $_userByName = array();
        if (!isset(self::$_userByName[$username])) {
            $_userByName[$username] = Users::model()->findByAttributes(array('username'=>$username));
        }
        return $_userByName[$username];
    }

    /**
     * Return safe user data.
     * @param user id not required
     * @return user object or false
     */
    public function users() {
        return Users;
    }

    public function filters()
    {
        return array(
            'accessControl',
        );
    }

// public function accessRules()
// {
//     return array(
//         array('allow',
//             'users'=>array('*'),
//             'actions'=>array('login'),
//         ),
//         array('allow',
//             'users'=>array('@'),
//         ),
//         array('deny',
//             'users'=>array('*'),
//         ),
//     );
// }

//    public function beforeAction($action)
//	{
//	    // If application is using a theme, replace default layout controller variable that start with '//layouts/' with a theme link
//	    if(empty(Yii::app()->theme->name) == false && isset($this->layout) == true && strpos($this->layout, '//layouts/') === 0)
//	    {
//	        // Replace path with slash by dot.
//	        $sThemeLayout = 'webroot.themes.'.Yii::app()->theme->name.'.views.layouts.'.str_replace('/', '.', substr($this->layout,10));
//
//	        // If theme override given layout, get it from theme
//	        if($this->getLayoutFile($sThemeLayout) !== false)
//	            $this->layout = $sThemeLayout;
//	    }
//	    return true;
//	}
}

