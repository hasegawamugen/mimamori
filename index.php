<?php
//https://github.com/hasegawamugen/mimamori
//********************************************************************
// 機能名　　： 情報入力・登録処理
// ファイル名： index
// 格納場所　： /
//********************************************************************
ini_set("display_errors",1);
//********************************************************************
define("LOGIN_SESSION","hemslogin");
session_start();
//	共通インクルード
require_once("../../_syslib_family/inc/com_def.inc");
require_once(SYS_PATH . "inc/com_db.inc");
require_once(SYS_PATH . "inc/com_smarty.inc");
require_once(SYS_PATH . "inc/com_forms.inc");
require_once(SYS_PATH . "inc/com_func.inc");
require_once(SYS_PATH . "inc/com_mobile.inc");
require_once(SYS_PATH . "inc/com_login.inc");
//====================================================================
ini_set("error_log",SYS_PATH . "logs/phperror.txt");
//====================================================================

define("DEFAULT_TYPE","index");	//	デフォルトのtypeパラメータ
define("LIB_PATH",SYS_PATH . "lib/www/");
define("IMAGE_SUBDIR","");

//====================================================================
//	テンプレート設定
$mobile	= new MobileInfo();
if($_SESSION["mobile"]["carrier"]){
	//携帯
	$smarty = new SmartyEx("sjis");
	define("ENCODING_TRANSLATION",true);
	define("TEMPLATE_ENCODING","UTF-8");
	define("TEMPLATE_ROOT","www_sp/");
} elseif($_SESSION["mobile"]["agent"] == "smartphone"){
	//スマホ(2012.2.9)
	$smarty = new SmartyEx();
	define("ENCODING_TRANSLATION",true);
	define("TEMPLATE_ENCODING","UTF-8");
	define("TEMPLATE_ROOT","www_sp/");
} else {
	//PC
	$smarty = new SmartyEx();
	define("ENCODING_TRANSLATION",true);
	define("TEMPLATE_ENCODING","UTF-8");
	define("TEMPLATE_ROOT","www/");
}

//---------------------------------
//	編集ページタイプ
//---------------------------------
$page_type	= $_REQUEST["type"];
if(!$page_type)	$page_type = DEFAULT_TYPE;
if(!$page_type || !file_exists(LIB_PATH . "db_" . $page_type . ".inc")){
	//	存在しない⇒静的ページ
	//$_GET["file"] = $page_type . ".html";
	//$page_type = SUB_PATH . "_static";
	header("Location: /");
	exit();
}
//---------------------------------
//	管理対象別DB操作クラス
//---------------------------------
require_once(LIB_PATH . basename("db_" . $page_type . ".inc"));
//---------------------------------
if($dbconn = new DBConnectionEX()){
	//---------------------------------
	//	管理対象別フォーム設定
	//---------------------------------
	require_once(LIB_PATH . basename("forms_" . $page_type . ".inc"));
	//---------------------------------
	//	フォームデータの取得
	//---------------------------------
	if($_SERVER["REQUEST_METHOD"] == "GET"){
		//---------------------------------
		$_SESSION["X_PAGEID"] = uniqid(chr(mt_rand(97,122)));	//	XSRF対策
		//---------------------------------
		//	表示
		//---------------------------------
		$xmode = ($_GET["execmode"] ? $_GET["execmode"] : DEFAULT_XMODE);
		//	特殊パラメータ
		if(isset($_GET["x-vew"])){
			$xmode = "vew";
			$_GET["cd"] = $_GET["x-vew"];
		} elseif(isset($_GET["x-del"])){
			$xmode = "del";
			$_GET["cd"] = $_GET["x-del"];
		} elseif(isset($_GET["x-reg"])){
			$xmode = "reg";
			$_GET["cd"] = $_GET["x-reg"];
		} elseif(isset($_GET["x-src"])){
			$xmode = "src";
		} elseif(isset($_GET["x-lst"])){
			$xmode = "lst";
			$_GET["cd"] = $_GET["x-lst"];
		}
		//---------------------------------
		switch($xmode){
			//---------------------------------
			case "cnf" :	//	確認画面
			//---------------------------------
				$forms = new FormData($arFields);
				if($_SESSION["reg_data"][$page_type]) {
					//	保持済みデータのセット
					$arDefault	= array_merge($arDefault,$_SESSION["reg_data"][$page_type]);
				}
				//	確認の前処理
				if(method_exists($dbconn,"PrepareConfirm"))	$arDefault = array_merge($arDefault,$dbconn->PrepareConfirm($arDefault));
				$arForm	= array_merge($arDefault,$forms->GetData($arDefault,"TEXT"));
				$template	= (defined("TEMPLATE_CNF") ? TEMPLATE_CNF : $page_type . $arForm["page_suffix"] . "_cnf.html");
				break;
			//---------------------------------
			case "fin" :	//	完了画面
			//---------------------------------
				if($arForm = $_SESSION["reg_data"][$page_type]){
					$arForm	= array_merge($arForm,$dbconn->BeforeFinish($_SESSION["reg_data"][$page_type]));
				}
				$template	= (defined("TEMPLATE_FIN") ? TEMPLATE_FIN : $page_type . $arForm["page_suffix"] . "_fin.html");
				unset($_SESSION["reg_data"][$page_type]);
				break;
			//---------------------------------
			case "reg" :	//	入力画面
			//---------------------------------
				$forms  = new FormData($arFields);
				if($_GET["clear"] == "no" && $_SESSION["reg_data"][$page_type]){
					//	保持済みデータで上書き
					$arDefault	= array_merge($arDefault,$dbconn->GetSingle($_SESSION["reg_data"][$page_type]));
					foreach($_SESSION["reg_data"][$page_type] as $ik => $val){
						//if($val != ""){		//	更新時に空欄化したものが戻ったときに元の状態に戻るのでコメント(2007.2.24)
							$arDefault[$ik] = $val;
						//}
					}
				} else {
					//	初期表示⇒デフォルトで初期状態(2008.6.1)
					if($_SESSION["reg_data"][$page_type]["ErrorMessage"]){
						$arFData = array_merge($_SESSION["reg_data"][$page_type],$_GET);	//	エラーリダイレクト時など
					} else {
						unset($_SESSION["reg_data"][$page_type]);	//	初期アクセス時
						$arFData = $_GET;
					}
					$arDefault	= array_merge($arDefault,$arFData);
					$arDefault	= array_merge($arDefault,$dbconn->GetSingle($arDefault));
				}
				$arForm	= array_merge($arDefault,$forms->GetData($arDefault));
				if($_SESSION["reg_data"][$page_type]["ErrorMessage"]){
					//	エラー表示
					if(defined("TEMPLATE_ERR")){
						$template	= TEMPLATE_ERR;
					} else {
						$template	= (defined("TEMPLATE_REG") ? TEMPLATE_REG : $page_type . $arForm["page_suffix"] . "_reg.html");
					}
					$smarty->set_error_message($_SESSION["reg_data"][$page_type]["ErrorMessage"]);
					$smarty->assign("cd",$_SESSION["reg_data"][$page_type]["cd"]);
					unset($_SESSION["reg_data"][$page_type]["ErrorMessage"]);
				} else {
					$template	= (defined("TEMPLATE_REG") ? TEMPLATE_REG : $page_type . $arForm["page_suffix"] . "_reg.html");
				}
				break;
			//---------------------------------
			case "src" :	//	検索画面
			//---------------------------------
				$forms  = new FormData($arSearchFields);
				if($_GET["clear"] == "yes"){
					unset($_SESSION["vew_search"][$page_type]);
				} elseif($_GET["clear"] == "no") {
					//	検索条件を保持した状態
					$arSearchDefault	= $_SESSION["vew_search"][$page_type];
				}
				$arSearchDefault = array_merge($arSearchDefault,$_GET);
				if(method_exists($dbconn,"PrepareSearch"))	$arSearchDefault = array_merge($arSearchDefault,$dbconn->PrepareSearch($arSearchDefault));
				$arForm	= array_merge($arSearchDefault,$forms->GetData($arSearchDefault));
				$template	= (defined("TEMPLATE_SRC") ? TEMPLATE_SRC : $page_type . $arForm["page_suffix"] . "_src.html");
				break;
			//---------------------------------
			case "lst" :	//	検索結果画面
			//---------------------------------
				$forms  = new FormData($arSearchFields);
				if($_GET["clear"] == "yes")	unset($_SESSION["vew_search"][$page_type]);
				$arData = ($_SESSION["vew_search"][$page_type] ? array_merge($_SESSION["vew_search"][$page_type],$_GET) : $_GET);
				$s_mode	= ($_GET["retry"] == "yes" ? "first" : "none");
				//	フォームデータのチェック
				if($forms->Validate($arData)){
					$forms->ResetDate($arData);	//	日付型フォームをリセット
					//	検索条件実行
					$arData["page"]	= (isset($arData["page"]) ? $arData["page"] : ($_SESSION["vew_search"][$page_type]["page"] ? $_SESSION["vew_search"][$page_type]["page"] : 0));
					$arData["items_per_page"]	= (defined("PAGE_LIMIT") ? PAGE_LIMIT : 20);
					unset($arData["total_count"]);
					$arList	= $dbconn->ExecuteSearch($arData,$s_mode);
					$_SESSION["vew_search"][$page_type]	= array_merge(($_SESSION["vew_search"][$page_type] ? $_SESSION["vew_search"][$page_type] : array()),$arData);
					//	ページング処理
					$smarty->assign("Paging",GetPaging($arData,$page_type));
					//
					$smarty->assign_array($arList);
					$smarty->assign_array($arData);
					$template	= (defined("TEMPLATE_LST") ? TEMPLATE_LST : $page_type . $arData["page_suffix"] . "_lst.html");
				} else {
					//	入力エラーあり
					$_SESSION["vew_search"][$page_type] = $arData;
					$_SESSION["vew_search"][$page_type]["ErrorMessage"] = join("<br />",$forms->ErrorMessage);
					$arForm	= array_merge($_GET,$forms->GetData($arSearchDefault));
					$template	= (defined("TEMPLATE_SRC") ? TEMPLATE_SRC : $page_type . $arForm["page_suffix"] . "_src.html");
				}
				break;
			//---------------------------------
			case "vew" :	//	詳細画面
			//---------------------------------
				$forms  = new FormData($arFields);
				$arData	= $dbconn->GetDetailView($_GET);
				$arForm	= array_merge($arData,$forms->GetData($arData,"TEXT"));
				$template	= (defined("TEMPLATE_VEW") ? TEMPLATE_VEW : $page_type . $arForm["page_suffix"] . "_vew.html");
				break;
			//---------------------------------
			case "del" :	//	削除確認画面
			//---------------------------------
				$forms  = new FormData($arFields);
				$arData	= $dbconn->GetDetailView($_GET);
				$arForm	= array_merge($arData,$_GET,$forms->GetData($arData,"TEXT"));
				$template	= (defined("TEMPLATE_DEL") ? TEMPLATE_DEL : $page_type . $arForm["page_suffix"] . "_del.html");
				break;
			//---------------------------------
			case "dwl" :	//	削除確認画面
			//---------------------------------
				require_once(SYS_PATH . "inc/com_csv_download.inc");
				$oCSV	= new CSVDownload();
				//---------------------------------
				if(is_array($_SESSION["vew_search"][$page_type])){
					$arData	= array_merge($_SESSION["vew_search"][$page_type],$_GET);
				} else {
					$arData = $_GET;
				}
				//---------------------------------
				if(method_exists($dbconn,"ExecuteDownload")){
					//	CSVフィールドの設定と実行を一度に行う
					$dbconn->ExecuteDownload($arData,$oCSV);
				}
				exit();
				break;
			//---------------------------------
			default :	//	メニュー画面
			//---------------------------------
				//	各種セッション破棄
				unset($_SESSION["reg_data"][$page_type]);
				unset($_SESSION["vew_search"][$page_type]);
				if ($page_type == "electricity")
				{
					$arForm	= $dbconn->PrepareMenu($_GET);
				}
				else
				{
					$arForm	= $dbconn->GetDetailView($_GET);
				}
				$template	= (defined("TEMPLATE_MNU") ? TEMPLATE_MNU : $page_type . $arForm["page_suffix"] . "_mnu.html");
			//---------------------------------
		}
		//---------------------------------
		//	強制エラー
		//---------------------------------
		if($arForm["err"]){
			if(defined("TEMPLATE_ERR")){
				$template	= TEMPLATE_ERR;
			} else {
				$template	= (defined("TEMPLATE_REG") ? TEMPLATE_REG : $page_type . $arForm["page_suffix"] . "_reg.html");
			}
		}
		//---------------------------------
	} else {
		//---------------------------------
		//	POST時の処理
		//---------------------------------
		//	XSRF対策
		//if($_POST["PAGEID"] != $_SESSION["X_PAGEID"]){
		//	header("Location: " . HTTP_URL . "transaction_error.html");
		//	exit();
		//}
		unset($_SESSION["X_PAGEID"]);	//	2重POST防止
		//---------------------------------
		//	サニタイジング
		//---------------------------------
		//---------------------------------
		if($_POST["params"]){
			parse_str($_POST["params"],$arParam);
			$arData = array_merge($arParam,$_POST);
		} else {
			$arData = $_POST;
		}
//error_log(var_export($arData,true),3,SYS_PATH . "logs/dump.log");
		//---------------------------------
		foreach($arData as $ik => $iv){
			//---------------------------------
			//	文字エンコード変換
			//---------------------------------
			if(ENCODING_TRANSLATION && !is_array($iv)){
				$iv = mb_convert_kana($iv,"KV",TEMPLATE_ENCODING);
				$iv = mb_convert_encoding($iv,mb_internal_encoding(),TEMPLATE_ENCODING);
			}
			//---------------------------------
			$arData[$ik] = $iv;
		}
		switch($_REQUEST["execmode"]){
			//---------------------------------
			case "Confirm" :	//	確認画面
			//---------------------------------
				$forms  = new FormData($arFields);
				//if(method_exists($dbconn,"PrepareConfirm"))	$arData = array_merge($arData,$dbconn->PrepareConfirm($arData));
				$forms->ExtraValidate	= ExtraValidate;	//	拡張チェック関数
				//	フォーム内容の整合性チェック
				if($forms->Validate($arData)){
					$_SESSION["reg_data"][$page_type] = $arData;
					//	入力確認ＯＫ
					unset($_SESSION["reg_data"][$page_type]["ErrorMessage"]);
					//$arData	= array_merge($arData,$forms->GetData($arData,"TEXT"));
					$default_mode = "cnf";
				} else {
					//	入力エラーあり
					$_SESSION["reg_data"][$page_type] = $arData;
					$_SESSION["reg_data"][$page_type]["ErrorMessage"] = join("<br />",$forms->ErrorMessage);
					$default_mode = "reg";
					$arData["next_mode"] = $default_mode;
					$arData["next_type"] = $page_type;
				}
				break;
			//---------------------------------
			case "ConfirmAjax" :	//	確認画面(AJAX版)
			//---------------------------------
				$forms = new FormData($arFields);
				$forms->ExtraValidate	= ExtraValidate;	//	拡張チェック関数
				$arList = array();
				if($forms->Validate($arData)){	//	フォーム内容の整合性チェック
					$arList[] = '<validate code="0" name="' . $arData["_form_name"] . '" />';
					$status = 0;
				} else {
					if(count($forms->xmlErrorMessage) > 0){
						$tag  = '<validate code="99" name="' . $arData["_form_name"] . '">';
						foreach($forms->xmlErrorMessage as $err){
							$tag .= '<error field="' . $err["name"] . '">' . htmlspecialchars($err["error"]) . '</error>';
						}
						$tag .= '</validate>';
						$arList[] = $tag;
						$status = 99;
					} elseif($forms->AlertMessage){
						$tag  = '<validate code="98" name="' . $arData["_form_name"] . '">';
						$tag .= join("\n",$forms->AlertMessage);
						$tag .= '</validate>';
						$arList[] = $tag;
						$status = 98;
					}
				}
				//	レスポンス出力
				header("Content-Type: text/xml; charset=utf-8");
				echo '<?xml version="1.0" encoding="UTF-8" ?>';
				echo '<Response status="' . intval($status) . '">';
				foreach($arList as $elms){
					echo $elms;
				}
				echo '</Response>';
				exit();
				break;
			//---------------------------------
			case "ConfirmJSON" :	//	確認画面(JSON版)
			//---------------------------------
				$forms = new FormData($arFields);
				$forms->ExtraValidate	= ExtraValidate;	//	拡張チェック関数
				$arList = array();
				if($forms->Validate($arData)){	//	フォーム内容の整合性チェック
					$Response = array(
						"status" => "0",
						"form_name" => $arData["_form_name"],
					);
				} else {
					if(count($forms->xmlErrorMessage) > 0){
						$Err = array();
						foreach($forms->xmlErrorMessage as $err){
							$Err[$err["name"]] = $err["error"];
						}
						$Response = array(
							"status" => "99",
							"form_name" => $arData["_form_name"],
							"message" => $Err,
						);
					} elseif($forms->AlertMessage){
						$Response = array(
							"status" => "98",
							"form_name" => $arData["_form_name"],
							"message" => $forms->AlertMessage,
						);
					}
				}
				//	レスポンス出力
				$response_str = json_encode($Response);
				header("Content-Type: application/json");
				header("Content-Length: " . strlen($response_str));
				echo $response_str;
				exit();
				break;
			//---------------------------------
			case "Register" :	//	登録完了画面
			//---------------------------------
				if($_SESSION["reg_data"][$page_type]){
					$arData = array_merge($_SESSION["reg_data"][$page_type],$arData);
				}
				$forms  = new FormData($arFields);
				$forms->ExtraValidate	= ExtraValidate;	//	拡張チェック関数
				//	フォーム内容の整合性チェック
				if($forms->Validate($arData)){
					//	登録処理
					$forms->ResetDate($arData);	//	日付型フォームをリセット
					$err = $dbconn->Register($arData,array_keys($forms->arForm));
					if(!$err){
						//	登録完了
						$_SESSION["reg_data"][$page_type] = $arData;
						unset($_SESSION["reg_data"][$page_type]["ErrorMessage"]);
						$default_mode = "fin";
					} else {
						//	登録時エラー発生
						$_SESSION["reg_data"][$page_type] = $arData;
						$_SESSION["reg_data"][$page_type]["ErrorMessage"] = $err;
						$default_mode = "reg";
						$arData["next_mode"] = $default_mode;
						$arData["next_type"] = $page_type;
					}
				} else {
					//	入力エラーあり
					$_SESSION["reg_data"][$page_type] = $arData;
					$_SESSION["reg_data"][$page_type]["ErrorMessage"] = join("<br />",$forms->ErrorMessage);
					$default_mode = "reg";
					$arData["next_mode"] = $default_mode;
					$arData["next_type"] = $page_type;
				}
				break;
			//---------------------------------
			case "Search" :	//	検索
			//---------------------------------
				$forms  = new FormData($arSearchFields);
				$s_mode	= "first";
				//	フォームデータのチェック
				if($forms->Validate($arData)){
					$forms->ResetDate($arData);	//	日付型フォームをリセット
					//	検索条件を保持してリダイレクト
					//$arData["page"]	= (isset($arData["page"]) ? $arData["page"] : ($_SESSION["vew_search"]["page"] ? $_SESSION["vew_search"]["page"] : 0));
					$arData["page"] = 0;
					$arData["items_per_page"]	= (defined("PAGE_LIMIT") ? PAGE_LIMIT : 20);
					$_SESSION["vew_search"][$page_type]	= array_merge(($_SESSION["vew_search"][$page_type] ? $_SESSION["vew_search"][$page_type] : array()),$arData);
					$default_mode = "lst";
				} else {
					//	入力エラーあり
					$_SESSION["vew_search"][$page_type] = $arData;
					$_SESSION["vew_search"][$page_type]["ErrorMessage"] = join("<br />",$forms->ErrorMessage);
					$default_mode = "src";
					$arData["next_mode"] = $default_mode;
					$arData["next_type"] = $page_type;
				}
				break;
			//---------------------------------
			case "Upload" :	//	アップロード処理
			//---------------------------------
				$dbconn->UpRegister($arData);
				exit();
				break;
			//---------------------------------
			case "Delete" :	//	削除
			//---------------------------------
				//	削除処理
				if($err = $dbconn->Delete($arData)){
					$_SESSION["reg_data"][$page_type]["ErrorMessage"] = $err;
				} else {
					$_SESSION["reg_data"][$page_type] = $arData;
				}
				$default_mode = "fin";
				break;
			//---------------------------------
			case "GetWetherXML" :	//	お天気情報取得(XML版)
			//---------------------------------
				$weatherXML = file_get_contents("http://weather.service.msn.com/data.aspx?src=vista&weadegreetype=C&culture=ja-JP&wealocations=wc:JAXX0104");

				header("Content-Type: text/xml");
				header("Content-Length: " . strlen($weatherXML));
				echo $weatherXML;
				exit();
				break;

			//---------------------------------
			case "GetWetherExJSON" :	//	お天気情報取得(JSON版)
			//---------------------------------
				$weatherJSON = file_get_contents("http://api.openweathermap.org/data/2.5/weather?q=Sendai,jp");

				header("Content-Type: application/json");
				header("Content-Length: " . strlen($weatherJSON));
				echo $weatherJSON;
				exit();
				break;

			//---------------------------------
			case "GetMimamoriJSON" :	//	見守り情報取得(JSON版)
			//---------------------------------
				$returnValue = $dbconn->GetDemoData($_POST["start_time"], $_POST["end_time"]);
				$jsonStr = json_encode($returnValue);

				header("Content-Type: application/json");
				header("Content-Length: " . strlen($jsonStr));
				echo $jsonStr;
				exit();
				break;

			//---------------------------------
			case "GetMotionJSON" :	//	モーション情報取得(JSON版)
			//---------------------------------
				//$jsonStr = '{ "motion": [  { "x": "2", "y": "1" }, { "x": "2", "y": "1" }, { "x": "2", "y": "2" }, { "x": "2", "y": "2" }, { "x": "2", "y": "2" }, { "x": "1", "y": "2" }, { "x": "1", "y": "2" }, { "x": "1", "y": "2" }, { "x": "1", "y": "2" } ] }';

				$jsonStr = '[{';
				$jsonStr .= '"motion": [';
				$jsonStr .= '{ "x": "2", "y": "0", "visible": "on" }, { "x": "2", "y": "0", "visible": "on" }, { "x": "1", "y": "1", "visible": "on" }, { "x": "1", "y": "1", "visible": "on" }, { "x": "0", "y": "1", "visible": "on" }, { "x": "0", "y": "1", "visible": "on" }, { "x": "-1", "y": "1", "visible": "on" }, { "x": "-1", "y": "1", "visible": "on" }, { "x": "-2", "y": "1", "visible": "on" }, { "x": "-2", "y": "1", "visible": "on" },';
				$jsonStr .= '{ "x": "-2", "y": "2", "visible": "on" }, { "x": "-2", "y": "2", "visible": "on" }, { "x": "-2", "y": "3", "visible": "on" }, { "x": "-2", "y": "3", "visible": "on" }, { "x": "-2", "y": "2", "visible": "on" }, { "x": "-2", "y": "2", "visible": "on" }, { "x": "-2", "y": "3", "visible": "on" }, { "x": "-2", "y": "3", "visible": "on" }, { "x": "-2", "y": "2", "visible": "on" }, { "x": "-2", "y": "2", "visible": "on" },';
				$jsonStr .= '{ "x": "-2", "y": "1", "visible": "on" }, { "x": "-2", "y": "1", "visible": "on" }, { "x": "-1", "y": "1", "visible": "on" }, { "x": "-1", "y": "1", "visible": "on" }, { "x": "0", "y": "1", "visible": "on" }, { "x": "0", "y": "1", "visible": "on" }, { "x": "1", "y": "0", "visible": "on" }, { "x": "1", "y": "0", "visible": "on" }, { "x": "2", "y": "0", "visible": "on" }, { "x": "2", "y": "0", "visible": "on" }';
				$jsonStr .= ']';
				$jsonStr .= '},{';
				$jsonStr .= '"motion": [';
				$jsonStr .= '{ "x": "2", "y": "0", "visible": "off" }, { "x": "2", "y": "0", "visible": "off" }, { "x": "2", "y": "0", "visible": "off" }, { "x": "2", "y": "0", "visible": "off" }, { "x": "2", "y": "0", "visible": "off" }, { "x": "2", "y": "0", "visible": "off" }, { "x": "2", "y": "0", "visible": "off" }, { "x": "2", "y": "0", "visible": "off" }, { "x": "2", "y": "0", "visible": "off" }, { "x": "2", "y": "0", "visible": "off" },';
				$jsonStr .= '{ "x": "2", "y": "0", "visible": "on" }, { "x": "2", "y": "0", "visible": "on" }, { "x": "2", "y": "0", "visible": "on" }, { "x": "2", "y": "0", "visible": "on" }, { "x": "1", "y": "0", "visible": "on" }, { "x": "1", "y": "0", "visible": "on" }, { "x": "0", "y": "1", "visible": "on" }, { "x": "0", "y": "1", "visible": "on" }, { "x": "1", "y": "0", "visible": "on" }, { "x": "1", "y": "0", "visible": "on" },';
				$jsonStr .= '{ "x": "1", "y": "1", "visible": "on" }, { "x": "1", "y": "1", "visible": "on" }, { "x": "0", "y": "2", "visible": "on" }, { "x": "0", "y": "2", "visible": "on" }, { "x": "1", "y": "1", "visible": "on" }, { "x": "1", "y": "1", "visible": "on" }, { "x": "2", "y": "0", "visible": "on" }, { "x": "2", "y": "0", "visible": "on" }, { "x": "2", "y": "0", "visible": "off" }, { "x": "2", "y": "0", "visible": "off" }';
				$jsonStr .= ']';
				$jsonStr .= '}]';

				header("Content-Type: application/json");
				header("Content-Length: " . strlen($jsonStr));
				echo $jsonStr;
				exit();
				break;

			//---------------------------------
			case "SendDemoAlert" :	//	Alert情報送信
			//---------------------------------
				$returnValue = $dbconn->SendDemoAlert($_POST["switch_value"]);
				$jsonStr = json_encode($returnValue);

				header("Content-Type: application/json");
				header("Content-Length: " . strlen($jsonStr));
				echo $jsonStr;
				exit();
				break;

			//---------------------------------
		}
		//---------------------------------
		//	GETリダイレクト
		//---------------------------------
//error_log(var_export($arData,true),3,SYS_PATH . "logs/dump.log");
		$next_type = ($arData["next_type"] ? $arData["next_type"] : $page_type);
		$next_mode = ($arData["next_mode"] ? $arData["next_mode"] : $default_mode);
		header("Location: " . $_SERVER["PHP_SELF"] . "?type=" . $next_type . ($next_mode ? "&execmode=" . $next_mode : "") . ($arData["page"] ? "&page=" . $arData["page"] : "") . (SID ? "&" . SID : ""));
		exit();
		//---------------------------------
	}
	$smarty->assign_array($arForm);
	//---------------------------------
	//$dbconn->Close();
}
//---------------------------------
$smarty->assign("login",$_SESSION[LOGIN_SESSION]);
$smarty->assign("PAGEID",$_SESSION["X_PAGEID"]);	//	XSRF対策
$smarty->assign("type",$page_type);
//---------------------------------
//	画面表示
//---------------------------------
//	2013.6.26
//header("Expires: Thu, 01 Dec 1994 16:00:00 GMT");
//header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
//header("Cache-Control: no-cache, must-revalidate");
//header("Cache-Control: post-check=0, pre-check=0", false);
//header("Pragma: no-cache");
//
$smarty->display(TEMPLATE_ROOT . TEMPLATE_DIR . $template);
//---------------------------------
//	ページング処理関数
//	(アプリケーションにより要カスタマイズ)
//---------------------------------
function GetPaging($arData,$page_type,$page_limit=5){
	$arRetVal = array();
	$arRetVal["CountFrom"] = ($_SESSION["vew_search"][$page_type]["total_count"] ? $arData["page"] * $arData["items_per_page"] + 1 : 0);
	$arRetVal["CountTo"] = min($arRetVal["CountFrom"] + $arData["items_per_page"] - 1,$_SESSION["vew_search"][$page_type]["total_count"]);
	$arRetVal["TotalCount"] = $_SESSION["vew_search"][$page_type]["total_count"];
	//---------------------------------
	$max_page	= floor($_SESSION["vew_search"][$page_type]["total_count"] / $arData["items_per_page"]);
	$arData["total_page"]	= ($max_page > 0 ? $max_page + 1 : 1);
	$self_url	= $_SERVER["PHP_SELF"] . '?type=' . $page_type . '&execmode=lst&page=';
	if($_SESSION["vew_search"][$page_type]["total_count"] % $arData["items_per_page"] == 0)	$max_page--;
	if($arData["page"] >= 3){
		$stPage	= $arData["page"] - 3;
		$stPage	= ($max_page - $stPage < $page_limit ? $max_page - $page_limit : $stPage);
	} else {
		$stPage	= 0;
	}
	//---------------------------------
	if($stPage > 0){
		$arPageStr[] = '<span style="border-left:none;">…</span>';
	}
	for($i=0;$i<$page_limit;$i++){
		$tPage	= $i + $stPage;
		if($tPage > $max_page)	break;
		if($tPage < 0)	continue;
		if($tPage == $arData["page"]){
			$arPageStr[]	= '<span class="list-step-active">' . ($tPage + 1) . '</span>';	//	現在のページ
		} else {
			$arPageStr[]	= '<span class="list-step"><a href="' . $self_url . $tPage . '">' . ($tPage + 1) . '</a></span>';	//	別のページ
		}
	}
	if($stPage + $page_limit < $max_page){
		$arPageStr[] = '<li>…</li>';
	}
	//---------------------------------
	if($arPageStr){
		$page_str	= ' ' . join("\n",$arPageStr) . ' ';
		if($arData["page"] > 0){
			$arRetVal["PrevStep"] = '<span class="list-prev"><a href="' . $self_url . ($arData["page"] - 1) . '"><img src="images/btn_dateprev.png" width="12" height="12" /></a></span>';
			$arRetVal["PrevStepSimple"] = '<a href="' . $self_url . ($arData["page"] - 1) . '">&lt;&lt;前へ</a>';
			$arRetVal["PrevStepLink"] = '<a href="page/' . ($arData["page"] - 1) . '" class="list-paginate">&lt;&lt;前へ</a>';
			$arRetVal["PrevStepPage"] = $arData["page"] - 1;
		}
		if(($arData["page"] + 1) * $arData["items_per_page"] < $_SESSION["vew_search"][$page_type]["total_count"]){
			$lest = $_SESSION["vew_search"][$page_type]["total_count"] - (($arData["page"] + 1) * $arData["items_per_page"]);
			if($lest > $arData["items_per_page"]) $lest = $arData["items_per_page"];
			$arRetVal["NextStep"] = '<span class="list-next"><a href="' . $self_url . ($arData["page"] + 1) . '"><img src="images/btn_datenext.png" width="12" height="12" /></a></span>';
			$arRetVal["NextStepSimple"] = '<a href="' . $self_url . ($arData["page"] + 1) . '">次へ&gt;&gt;</a>';
			$arRetVal["NextStepLink"] = '<a href="page/' . ($arData["page"] + 1) . '" class="list-paginate">次へ&gt;&gt;</a>';
			$arRetVal["NextStepPage"] = $arData["page"] + 1;
		}
		if($max_page > 0)	$arRetVal["PageStep"] = $page_str;
	}
	//---------------------------------
	$arRetVal["TotalPages"] = $arData["total_page"];
	$arRetVal["CurrentPage"] = $arData["page"] + 1;
	//---------------------------------
	return $arRetVal;
}
?>