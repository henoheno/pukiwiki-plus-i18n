<?
/**
 * PukiWiki Plus! JugemKey 認証処理
 *
 * @copyright   Copyright &copy; 2006-2008, Katsumi Saito <katsumi@jo1upk.ymt.prug.or.jp>
 * @author      Katsumi Saito <katsumi@jo1upk.ymt.prug.or.jp>
 * @version     $Id: jugemkey.inc.php,v 0.12 2008/02/24 18:39:00 upk Exp $
 * @license     http://opensource.org/licenses/gpl-license.php GNU Public License (GPL2)
 */
require_once(LIB_DIR . 'auth_jugemkey.cls.php');

function plugin_jugemkey_init()
{
	$msg = array(
	  '_jugemkey_msg' => array(
		'msg_logout'		=> _("logout"),
		'msg_logined'		=> _("%s has been approved by JugemKey."),
		'msg_invalid'		=> _("The function of JugemKey is invalid."),
		'msg_not_found'		=> _("pkwk_session_start() doesn't exist."),
		'msg_not_start'         => _("The session is not start."),
		'msg_jugemkey'		=> _("JugemKey"),
		'btn_login'		=> _("LOGIN(JugemKey)"),
		'msg_userinfo'		=> _("JugemKey user information"),
		'msg_user_name'		=> _("User Name"),
	  )
	);
        set_plugin_messages($msg);
}

function plugin_jugemkey_convert()
{
	global $script,$vars,$auth_api,$_jugemkey_msg;

	if (! $auth_api['jugemkey']['use']) return '<p>'.$_jugemkey_msg['msg_invalid'].'</p>';

	if (! function_exists('pkwk_session_start')) return '<p>'.$_jugemkey_msg['msg_not_found'].'</p>';
	if (pkwk_session_start() == 0) return '<p>'.$_jugemkey_msg['msg_not_start'].'</p>';

	$obj = new auth_jugemkey();
	$name = $obj->auth_session_get();
	if (isset($name['title'])) {
		// $name = array('title','ts','token');
		$logout_url = $script.'?plugin=jugemkey';
		if (! empty($vars['page'])) {
			$logout_url .= '&amp;page='.rawurlencode($vars['page']).'&amp;logout';
		}

		return <<<EOD
<div>
        <label>JugemKey</label>:
	{$name['title']}
	(<a href="$logout_url">{$_jugemkey_msg['msg_logout']}</a>)
</div>

EOD;
        }

	// 他でログイン
	$auth_key = auth::get_user_name();
	if (! empty($auth_key['nick'])) return '';

	// ボタンを表示するだけ
	$login_url = $script.'?plugin=jugemkey';
	if (! empty($vars['page'])) {
		$login_url .= '&amp;page='.rawurlencode($vars['page']);
	}
	$login_url .= '&amp;login';

	return <<<EOD
<form action="$login_url" method="post">
	<div>
		<input type="submit" value="{$_jugemkey_msg['btn_login']}" />
	</div>
</form>

EOD;
}

function plugin_jugemkey_inline()
{
	global $script,$vars,$auth_api,$_jugemkey_msg;

	if (! $auth_api['jugemkey']['use']) return $_jugemkey_msg['msg_invalid'];

	if (! function_exists('pkwk_session_start')) return $_jugemkey_msg['msg_not_found'];
	if (pkwk_session_start() == 0) return $_jugemkey_msg['msg_not_start'];

	$obj = new auth_jugemkey();
        $name = $obj->auth_session_get();
	if (isset($name['title'])) {
		// $name = array('title','ts','token');
		$link = $name['title'];
		$logout_url = $script.'?plugin=jugemkey';
		if (! empty($vars['page'])) {
			$logout_url .= '&amp;page='.rawurlencode($vars['page']).'&amp;logout';
		}
		return sprintf($_jugemkey_msg['msg_logined'],$link) .
			'(<a href="'.$logout_url.'">'.$_jugemkey_msg['msg_logout'].'</a>)';
        }

	$auth_key = auth::get_user_name();
	if (! empty($auth_key['nick'])) return $_jugemkey_msg['msg_jugemkey'];

	$login_url = plugin_jugemkey_jump_url(1);
	return '<a href="'.$login_url.'">'.$_jugemkey_msg['msg_jugemkey'].'</a>';
}

function plugin_jugemkey_action()
{
	global $vars,$auth_api,$_jugemkey_msg;

	if (! $auth_api['jugemkey']['use']) return '';
	if (! function_exists('pkwk_session_start')) return '';
	if (pkwk_session_start() == 0) return '';

	$page = (empty($vars['page'])) ? '' : $vars['page'];

	$die_message = (PLUS_PROTECT_MODE) ? 'die_msg' : 'die_message';

	// LOGIN
	if (isset($vars['login'])) {
		header('Location: '. plugin_jugemkey_jump_url());
		die();
	}

	$obj = new auth_jugemkey();

	// LOGOUT
	if (isset($vars['logout'])) {
		$obj->auth_session_unset();
		header('Location: '. get_page_location_uri($page));
		die();
	}

	// Get token info
	if (isset($vars['userinfo'])) {
		$rc = $obj->get_userinfo($vars['token']);
		if ($rc['rc'] != 200) {
			$msg = (empty($rc['error'])) ? '' : ' ('.$rc['error'].')';
			$die_message('JugemKey: RC='.$rc['rc'].$msg);
		}

		$body = '<h3>'.$_jugemkey_msg['msg_userinfo'].'</h3>'.
			'<strong>'.$_jugemkey_msg['msg_user_name'].': '.$rc['title'].'</strong>';
		return array('msg'=>'JugemKey', 'body'=>$body);
	}

	// AUTH
	$rc = $obj->auth($vars['frob']);
	if ($rc['rc'] != 200) {
		$msg = (empty($rc['error'])) ? '' : ' ('.$rc['error'].')';
		$die_message('JugemKey: '.$rc['rc'].$msg);
	}

	$obj->auth_session_put();
	header('Location: '. get_page_location_uri($page));
	die();
}

function plugin_jugemkey_jump_url($inline=0)
{
	global $vars;
	$page = (empty($vars['page'])) ? '' : $vars['page'];
	$callback_url = get_location_uri('jugemkey',$page);
	$obj = new auth_jugemkey();
	$url = $obj->make_login_link($callback_url);
	return ($inline) ? $url : str_replace('&amp;','&',$url);
}

function plugin_jugemkey_get_user_name()
{
	global $auth_api;
        if (! $auth_api['jugemkey']['use']) return array('role'=>ROLE_GUEST,'nick'=>'');

	$obj = new auth_jugemkey();
	$login = $obj->auth_session_get();
	// FIXME
	// Because user information can be acquired by token only at online, it doesn't mount. 
	// $info = (empty($login['token'])) ? '' : get_resolve_uri('jugemkey','', '', 'token='.$login['token'].'%amp;userinfo');
	// Only, it leaves it only as a location of attestation by JugemKey.
	$info = 'http://jugemkey.jp/';
	if (! empty($login['title'])) return array('role'=>ROLE_AUTH_JUGEMKEY,'nick'=>$login['title'],'profile'=>$info,'key'=>$login['title']);
	return array('role'=>ROLE_GUEST,'nick'=>'');
}

?>
