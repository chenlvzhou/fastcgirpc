<?php
/**
 * fast RPC Server Side Dispatch framework
 *
 */

class FastRPCDispatch
{
	private $method;
	private $rpccfg;

	function __construct() {
		global $rpccfg;
		$this->rpccfg = &$rpccfg;

		ob_start();

		if(isset($this->rpccfg['display_error']) && $this->rpccfg['display_error']) {
			error_reporting(E_ALL ^ E_NOTICE);
			set_error_handler(frpc_error_handle, E_ALL ^ E_NOTICE);
		}else{
			error_reporting(0);
		}

	}

	function __destruct() {
		ob_end_flush();
	}

	function run() {

		$params = array();
		$method = $_SERVER['FRPC_METHOD'];
		if(empty($method)) {
			return false;
		}

		list($services, $handle) = explode('.', $method);
		if(empty($services)) {
			trigger_error('must spesifai services', E_USER_ERROR);
			return false;
		}

		if(empty($handle))
			$handle = 'index';
		else {
			if($handle[0] == '_') {
				trigger_error('invalid request [handle=' . $handle . ']', E_USER_ERROR);
				return false;			
			}
		}
		
		foreach($_SERVER as $key => $value) {
			if(0 == strncmp($key, 'FRPC_ARGS_', 10)) {
				$params[substr($key, 10)] = $_SERVER[$key];
			}
		}

		$class = $services . 'Services';
		$file = $this->rpccfg['services'] . '/' . $class . '.php';
		if(!file_exists($file)) {
			trigger_error('invalid services [' . $services . ']', E_USER_ERROR);
			return false;
		}

		include_once(dirname(__FILE__) . '/FastRPCServices.php');
		include_once($file);

		if(!class_exists($class))
			return false;

		$obj = new $class;

		if('FastRPCServices' != get_parent_class($obj))
		{
			trigger_error('parent is wrong!', E_USER_ERROR);
			return false;
		}

		if(!method_exists($obj, 'handle'. $handle))
		{
			trigger_error('[handle=' . $handle . '] not found', E_USER_ERROR);
			return false;
		}

		$ret = call_user_method_array('handle'. $handle, &$obj, array($params));
		ob_clean();
		if(false == $ret) {
			echo json_encode(array('state' => '300 failed', 'error' => $obj->getError()));
		}else{
			echo json_encode(array('state' => '200 success', 'entity' => $ret));
		}
	}

}

function frpc_error_handle($errno, $errstr, $errfile, $errline)
{
	ob_clean();
	echo json_encode(array('state' => '500 failed', 'error' => $errstr . ', file=' . $errfile . ', line=' . $errline));
	ob_end_flush();

	exit;
}

