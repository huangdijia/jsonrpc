<?php

namespace Huangdijia\JsonRpc;

use Exception;

class Server
{
    const JSONRPC_VERSION = '2.0';
    /**
     * @var mixed 請求參數
     */
    protected static $request = null;
    private static $debug     = false;

    /**
     * @param $errno 錯誤編碼
     * @param $errstr 錯誤信息
     * @param $errfile 文件路徑
     * @param $errline 文件行數
     * @param array $errcontext 內容
     */
    public static function errorHandler($errno, $errstr, $errfile, $errline, $errcontext = [])
    {
        // 记录日志
        $response = [
            'jsonrpc' => self::JSONRPC_VERSION,
            'result'  => null,
            'error'   => $errstr,
            'id'      => self::$request['id'],
        ];

        if (
            (error_reporting() & $errno)
            && self::isFatal($errno)
        ) {
            // 输出返回结果
            self::response($response);
            exit;
        }
    }

    /**
     * @param $e 異常對象
     */
    public static function exceptionHandler($e)
    {
        $error            = [];
        $error['message'] = $e->getMessage();
        $trace            = $e->getTraceAsString();
        $error['file']    = $e->getFile();
        $error['line']    = $e->getLine();

        $response = [
            'jsonrpc' => self::JSONRPC_VERSION,
            'result'  => null,
            'error'   => $e->getMessage(),
            'id'      => self::$request['id'],
        ];
        // 输出返回结果
        self::response($response);
        // 保存日誌
        exit;
    }

    public static function shutdownHandler()
    {
        if (
            !is_null($error = error_get_last())
            && self::isFatal($error['type'])
        ) {
            $errstr  = $error['message'];
            $errno   = $error['type'];
            $errfile = $error['file'];
            $errline = $error['line'];
            $log     = "{$errstr} [{$errno}] in {$errfile} on line {$errline}";
            // 记录日志
            $response = [
                'jsonrpc' => self::JSONRPC_VERSION,
                'result'  => null,
                'error'   => $errstr,
                'id'      => self::$request['id'],
            ];
            // 输出返回结果
            self::response($response);
            // 保存日誌
            Log::save();
            exit;
        }
    }

    /**
     * @param $type 錯誤類型
     */
    protected static function isFatal($type = 0)
    {
        return in_array($type, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE]);
    }

    /**
     * @param $object 對象
     */
    public static function handle($object)
    {
        // 检测是否 JSON-RCP 请求
        if (
            $_SERVER['REQUEST_METHOD'] != 'POST' ||
            empty($_SERVER['CONTENT_TYPE']) ||
            $_SERVER['CONTENT_TYPE'] != 'application/json'
        ) {
            // 非 JSON-RPC 请求
            return false;
        }
        // 从 input 获取请求数据
        self::$request = $request = json_decode(file_get_contents('php://input'), true);
        // 記錄請求方法
        $request_string = var_export($request, 1);
        $request_string = preg_replace('/\s+/', ' ', $request_string);
        // $request_string = strtr($request_string, ['array ( ' => '[ ', ', )' => ' ]']);
        // 执行请求
        try {
            // if ($result = @call_user_func_array([$object, $request['method']], $request['params'])) {
            //     $response = [
            //         'jsonrpc' => self::JSONRPC_VERSION,
            //         'result'  => $result,
            //         'error'   => null,
            //         'id'      => $request['id'],
            //     ];
            // } else {
            //     $response = [
            //         'jsonrpc' => self::JSONRPC_VERSION,
            //         'result'  => null,
            //         'error'   => 'unknown method or incorrect parameters',
            //         'id'      => $request['id'],
            //     ];
            // }
            $result   = @call_user_func_array([$object, $request['method']], $request['params']);
            $response = [
                'jsonrpc' => self::JSONRPC_VERSION,
                'result'  => $result,
                'error'   => null,
                'id'      => $request['id'],
            ];
        } catch (Exception $e) {
            self::exceptionHandler($e);
        }
        // 输出返回结果
        self::response($response);
        // 执行完成
        return true;
    }

    /**
     * @param array $response 返回數據
     * @return null
     */
    public static function response(array $response = [])
    {
        if (empty(self::$request['id'])) {
            return;
        }
        // 結果輸出
        header('content-type: text/javascript');
        echo json_encode($response);
    }
}
