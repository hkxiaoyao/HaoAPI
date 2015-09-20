<?php
ini_set('display_errors',1);            //错误信息
ini_set('display_startup_errors',1);    //php启动错误信息

error_reporting(-1);                    //打印出所有的 错误信息

date_default_timezone_set('PRC');//设定时区


    //加载配置文件
    require_once(__dir__.'/../config.php');

    //常用常量
    require_once(AXAPI_ROOT_PATH.'/components/constants.php');

    //数据库操作工具
    require_once(AXAPI_ROOT_PATH.'/lib/DBTool/DBModel.php');

    //加载基础方法
    require_once(AXAPI_ROOT_PATH.'/components/Utility.php');

    /**
     * 主要用于捕捉致命错误，每次页面处理完之后执行检查
     * @return [type] [description]
     */
    function catch_fatal_error()
    {
      // Getting Last Error
       $last_error =  error_get_last();

        // Check if Last error is of type FATAL
        if(isset($last_error['type']))
        {
            // Fatal Error Occurs
            // Do whatever you want for FATAL Errors
            $errorMsg = null;
            switch ($last_error['type']) {
                case E_ERROR:
                    $errorMsg = '严重错误：服务器此时无法处理您的请求，请稍后或联系管理员。';
                    break;
                case E_PARSE:
                    $errorMsg = '代码拼写错误：是Peter干的吗，请向管理员举报Peter。';
                    break;
                case E_WARNING:
                    $errorMsg = '警告：出现不严谨的代码逻辑，请告知管理员这个问题。';
                    break;
            }

            if (!is_null($errorMsg))
            {
                @ob_end_clean();//要清空缓冲区， 从而删除PHPs " 致命的错误" 消息。
                $results = Utility::getArrayForResults(RUNTIME_CODE_ERROR_UNKNOWN,$errorMsg,null,array('errorContent'=>'Error on line '.$last_error['line'].' in '.$last_error['file'].': '.$last_error['message'].''));
                echo json_encode($results, JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

    }
    register_shutdown_function('catch_fatal_error');

    //接口格式校验
    $results = Utility::getAuthForApiRequest();
    if ($results['errorCode']==RUNTIME_CODE_OK)
    {
        //调用对应接口方法
        try {
            $apiPaths = explode('/', preg_replace ("/(\/*[\?#].*$|[\?#].*$|\/*$)/", '', $_SERVER['REQUEST_URI']));
            if (count($apiPaths)<3)
            {
                list ($apiController, $apiAction) = explode ("/", W2HttpRequest::getRequestString('r',false,'/'), 2);
                // $results = Utility::getArrayForResults(RUNTIME_CODE_ERROR_PARAM,'错误的请求地址，请使用正确的[/对象/方法]地址。');
            }
            else
            {
                $apiController = $apiPaths[1];
                $apiAction = $apiPaths[2];
            }

            //记录接口日志
            file_put_contents(sprintf('%s/access-%s.log',AXAPI_ROOT_PATH.'/logs/',strftime('%Y%m%d'))
                                ,sprintf("[%s] [%s] [%d] [%s] [%s/%s]: %s\n"
                                            ,DateTime::createFromFormat('U.u', microtime(true))->setTimeZone(new DateTimeZone('+8'))->format('Y-m-d H:i:s.u e')
                                            ,$_SERVER['REMOTE_ADDR']
                                            ,Utility::getCurrentUserID()
                                            ,count($_POST)>0?'POST':'GET'
                                            ,$apiController, $apiAction
                                            ,count($_POST)>0?json_encode($_POST, JSON_UNESCAPED_UNICODE):json_encode($_GET, JSON_UNESCAPED_UNICODE)
                                        )
                                ,FILE_APPEND);


            //记录接口日志
            file_put_contents(sprintf('%s/access-%s.log',AXAPI_ROOT_PATH.'/logs/',strftime('%Y%m%d'))
                                ,sprintf("[%s] [%s] [%d] [%s] [%s/%s]: %s\n"
                                            ,DateTime::createFromFormat('U.u', microtime(true))->setTimeZone(new DateTimeZone('+8'))->format('Y-m-d H:i:s.u e')
                                            ,$_SERVER['REMOTE_ADDR']
                                            ,Utility::getCurrentUserID()
                                            ,count($_POST)>0?'POST':'GET'
                                            ,$apiController, $apiAction
                                            ,count($_POST)>0?json_encode($_POST, JSON_UNESCAPED_UNICODE):json_encode($_GET, JSON_UNESCAPED_UNICODE)
                                        )
                                ,FILE_APPEND);

            $method = new ReflectionMethod($apiController.'Controller', 'action'.$apiAction);
            $results = $method->invoke(null,0);
        } catch (Exception $e) {
            $results = Utility::getArrayForResults(RUNTIME_CODE_ERROR_UNKNOWN,$e->getMessage(),null,array('errorContent'=>'Error on line '.$e->getLine().' in '.$e->getFile().': '.$e->getMessage().''));
        }
    }

    //打印接口返回的数据
    if (is_array($results))
    {
        if (array_key_exists('errorCode',$results))
        {
            $data = $results['results'];
            if (is_object($results['results']) && is_subclass_of($results['results'],'AbstractModel'))
            {
                $data = $results['results']->properties();
            }
            else if (is_array($results['results']) && array_key_exists(0, $results['results']))
            {
                $data = array();
                foreach ($results['results'] as $_key => $_value) {
                    if (is_object($_value) && is_subclass_of($_value,'AbstractModel'))
                    {
                        $data[$_key] = $_value->properties();
                    }
                    else
                    {
                        $data[$_key] = $_value;
                    }
                }
            }
            $results['results'] = $data;
        }
        header('Content-Type:text/javascript; charset=utf-8');
        if (defined('IS_AX_DEBUG'))
        {
            print_r($results);
        }
        else
        {
            echo json_encode($results, JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
    else if (is_string($results))
    {
        echo $results;
        exit;
    }
    else
    {
        echo json_encode($results, JSON_UNESCAPED_UNICODE);
        exit;
    }

