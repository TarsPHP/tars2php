<?php
/**
 * 说明：
 *    1. } 要单独放在一行，如}后面有;号，要跟}放在一行
 *    2. 每行只能字义一个字段
 *    3. struct不能嵌套定义，如用到别的struct, 把对应的struct 拿出来定义即可.
 **/
$configFile = $argv[1];
$config = require_once $configFile;

$fileConverter = new FileConverter($config);

//$fileConverter->moduleScan();

$fileConverter->moduleParse();

class Utils
{
    public static function inIdentifier($char)
    {
        return ($char >= 'a' & $char <= 'z') |
        ($char >= 'A' & $char <= 'Z') |
        ($char >= '0' & $char <= '9') |
        ($char == '_');
    }

    public static function abnormalExit($level, $msg)
    {
        echo "[$level]$msg"."\n";
        exit;
    }

    public static function pregMatchByName($name, $line)
    {
        // 处理第一行,正则匹配出classname
        $Tokens = preg_split("/$name/", $line);

        $mathName = $Tokens[1];
        $mathName = trim($mathName, " \r\0\x0B\t\n{");

        preg_match('/[a-zA-Z][0-9a-zA-Z]/', $mathName, $matches);
        if (empty($matches)) {
            //Utils::abnormalExit('error',$name.'名称有误'.$line);
        }

        return $mathName;
    }

    public static function isReturn($char)
    {
        if ($char == "\n" || $char == '\r' || bin2hex($char) == '0a' || bin2hex($char) == '0b' ||
            bin2hex($char) == '0c' || bin2hex($char) == '0d') {
            return true;
        } else {
            return false;
        }
    }
}

class FileConverter
{
    public $moduleName;
    public $uniqueName;
    public $interfaceName;
    public $fromFile;
    public $outputDir;

    public $appName;
    public $serverName;
    public $objName;
    public $servantName;

    public $namespaceName;
    public $namespacePrefix;

    public $preStructs = [];
    public $preEnums = [];
    public $preConsts = [];
    public $preNamespaceStructs = [];
    public $preNamespaceEnums = [];

    public $package;

    public function __construct($config)
    {
        $this->fromFile = $config['tarsFiles'][0];
        if (empty($config['appName']) || empty($config['serverName']) || empty($config['objName'])) {
            Utils::abnormalExit('error', 'appName or serverName or objName empty!');
        }
        $this->servantName = $config['appName'].'.'.$config['serverName'].'.'.$config['objName'];

        $this->appName = $config['appName'];
        $this->serverName = $config['serverName'];
        $this->objName = $config['objName'];

        $this->outputDir = empty($config['dstPath']) ? './' : $config['dstPath'].'/';
        $this->outputProtocDir = empty($config['protocDstPath']) ? './' : $config['protocDstPath'].'/';

        $pos = strrpos($this->fromFile, '/', -1);
        $inputDir = substr($this->fromFile, 0, $pos);
        $this->inputDir = $inputDir;

        $this->namespacePrefix = $config['namespacePrefix'];
        $this->withServant = $config['withServant'];

        $this->initDir();

        $this->checkProtoc();
        $this->runProtoc();
    }

    /**
     * 首先需要初始化一些文件目录.
     *
     * @return [type] [description]
     */
    public function initDir()
    {
        if (strtolower(substr(php_uname('a'), 0, 3)) === 'win') {
            exec('mkdir '.$this->outputDir.$this->appName);
            exec('mkdir '.$this->outputDir.$this->appName.'\\'.$this->serverName);
            exec('DEL '.$this->outputDir.$this->appName.'\\'.$this->serverName.'\\*.*');
            exec('mkdir '.$this->outputDir.$this->appName.'\\'.$this->serverName);

            $this->moduleName = $this->appName.'\\'.$this->serverName;

            exec('mkdir '.$this->outputDir.$this->moduleName.'\\tars');
            exec('copy '.$this->fromFile.' '.$this->outputDir.$this->moduleName.'\\tars');
        } else {
            exec('mkdir '.$this->outputDir.$this->appName);
            exec('mkdir '.$this->outputDir.$this->appName.'/'.$this->serverName);
            exec('rm -rf '.$this->outputDir.$this->appName.'/'.$this->serverName);
            exec('mkdir '.$this->outputDir.$this->appName.'/'.$this->serverName);

            $this->moduleName = $this->appName.'/'.$this->serverName;

            exec('mkdir '.$this->outputDir.$this->moduleName.'/tars');
            exec('cp '.$this->fromFile.' '.$this->outputDir.$this->moduleName.'/tars');
        }

        $this->namespaceName = empty($this->namespacePrefix) ? $this->appName.'\\'.$this->serverName
            : $this->namespacePrefix.'\\'.$this->appName.'\\'.$this->serverName;

        $this->uniqueName = $this->appName.'_'.$this->serverName;
    }

    public function usage()
    {
        echo 'php proto2php.php tars.proto.php';
    }

    public function moduleParse()
    {
        $fp = fopen($this->fromFile, 'r');
        if (!$fp) {
            $this->usage();
            exit;
        }

        $this->package = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $packageFlag = strpos(strtolower($line), 'package');
            if ($packageFlag !== false) {
                $this->package = Utils::pregMatchByName('package', $line);
                $this->package = str_replace(';', '', $this->package);
            }

            // 正则匹配，发现是在service中
            $serviceFlag = strpos(strtolower($line), 'service');
            if ($serviceFlag !== false) {
                $name = Utils::pregMatchByName('service', $line);
                $interfaceName = $name.'Servant';

                $basePath = '/' . $this->package . '.' . $name . '/';

                // 需要区分一下生成server还是client的代码
                if ($this->withServant) {

                    $servantParser = new ServantParser($fp, $line, $this->namespaceName, $this->moduleName,
                        $interfaceName, $this->preStructs,
                        $this->preEnums, $this->servantName, $this->preNamespaceEnums, $this->preNamespaceStructs, ucfirst(str_replace('.', '\\', $this->package)));

                    $servant = $servantParser->parse();
                    file_put_contents($this->outputDir.$this->moduleName.'/'.$interfaceName.'.php', $servant);
                } else {

                    $interfaceParser = new InterfaceParser($fp, $line, $this->namespaceName, $this->moduleName,
                        $interfaceName, $this->preStructs,
                        $this->preEnums, $this->servantName, $this->preNamespaceEnums, $this->preNamespaceStructs, $basePath, ucfirst(str_replace('.', '\\', $this->package)));
                    $interfaces = $interfaceParser->parse();

                    // 需要区分同步和异步的两种方式
                    file_put_contents($this->outputDir.$this->moduleName.'/'.$interfaceName.'.php', $interfaces['syn']);
                }
            }
        }
    }

    public function checkProtoc()
    {
        $ret = exec("protoc --version", $ret2);
        if (empty($ret)) {
            echo "you have not install protoc, please check. doc : ";
            exit;
        }
    }

    public function runProtoc()
    {
//        $exec = "protoc --plugin=protoc-gen-grpc=/usr/local/bin/grpc_php_plugin --php_out={$this->outputDir} {$this->fromFile}";
        $exec = "protoc --php_out={$this->outputProtocDir} {$this->fromFile}";
        $ret = exec($exec);
    }
}

class InterfaceParser
{
    public $namespaceName;
    public $moduleName;
    public $interfaceName;
    public $asInterfaceName;

    public $state;

    // 这个结构体,可能会引用的部分,包括其他的结构体、枚举类型、常量
    public $useStructs = [];
    public $extraUse;
    public $preStructs;
    public $preEnums;

    public $preNamespaceStructs;
    public $preNamespaceEnums;

    public $returnSymbol = "\n";
    public $doubleReturn = "\n\n";
    public $tabSymbol = "\t";
    public $doubleTab = "\t\t";
    public $tripleTab = "\t\t\t";
    public $quardupleTab = "\t\t\t\t";

    public $extraContructs = '';
    public $extraExtInit = '';

    public $consts = '';
    public $variables = '';
    public $fields = '';

    public $funcSet = '';

    public $servantName;

    public $basePath = '';

    public function __construct($fp, $line, $namespaceName, $moduleName,
                                $interfaceName, $preStructs,
                                $preEnums, $servantName, $preNamespaceEnums, $preNamespaceStructs, $basePath, $paramNamespace)
    {
        $this->fp = $fp;
        $this->namespaceName = $namespaceName;
        $this->moduleName = $moduleName;
        $this->preStructs = $preStructs;
        $this->preEnums = $preEnums;
        $this->interfaceName = $interfaceName;
        $this->servantName = $servantName;

        $this->extraUse = '';
        $this->useStructs = [];

        $this->preNamespaceEnums = $preNamespaceEnums;
        $this->preNamespaceStructs = $preNamespaceStructs;
        $this->basePath = $basePath;

        $this->paramNamespace = $paramNamespace;
    }

    public function copyAnnotation()
    {
        // 再读入一个字符
        $nextChar = fgetc($this->fp);
        // 第一种
        if ($nextChar == '/') {
            while (1) {
                $tmpChar = fgetc($this->fp);

                if ($tmpChar == "\n") {
                    $this->state = 'lineEnd';
                    break;
                }
            }

            return;
        } elseif ($nextChar == '*') {
            while (1) {
                $tmpChar = fgetc($this->fp);

                if ($tmpChar === false) {
                    Utils::abnormalExit('error', '注释换行错误,请检查'.$tmpChar);
                } elseif ($tmpChar === "\n") {
                } elseif (($tmpChar) === '*') {
                    $nextnextChar = fgetc($this->fp);
                    if ($nextnextChar == '/') {
                        return;
                    } else {
                        $pos = ftell($this->fp);
                        fseek($this->fp, $pos - 1);
                    }
                }
            }
        }
        // 注释不正常
        else {
            Utils::abnormalExit('error', '注释换行错误,请检查'.$nextChar);
        }
    }

    public function getFileHeader($prefix = '')
    {
        return "<?php\n\nnamespace ".$this->namespaceName.$prefix.';'.$this->doubleReturn.
        'use Tars\\client\\CommunicatorConfig;'.$this->returnSymbol.
        'use Tars\\client\\Communicator;'.$this->returnSymbol.
        'use Tars\\client\\grpc\\GrpcRequestPacket;'.$this->returnSymbol.
        'use Tars\\client\\grpc\\GrpcResponsePacket;'.$this->returnSymbol;
    }

    public function getInterfaceBasic()
    {
        return $this->tabSymbol.'protected $_communicator;'.$this->returnSymbol.
        $this->tabSymbol.'protected $_iTimeout;'.$this->returnSymbol.
        $this->tabSymbol."public \$_servantName = \"$this->servantName\";".$this->returnSymbol.
        $this->tabSymbol."public \$_basePath = \"$this->basePath\";".$this->doubleReturn.
        $this->tabSymbol.'public function __construct(CommunicatorConfig $config) {'.$this->returnSymbol.

        $this->doubleTab.'try {'.$this->returnSymbol.
        $this->tripleTab.'$config->setServantName($this->_servantName);'.$this->returnSymbol.
        $this->tripleTab.'$this->_communicator = new Communicator($config);'.$this->returnSymbol.
        $this->tripleTab.'$this->_iTimeout = empty($config->getAsyncInvokeTimeout())?2:$config->getAsyncInvokeTimeout();'.$this->returnSymbol.
        $this->doubleTab.'} catch (\\Exception $e) {'.$this->returnSymbol.
        $this->tripleTab.'throw $e;'.$this->returnSymbol.
        $this->doubleTab.'}'.$this->returnSymbol.
        $this->tabSymbol.'}'.$this->doubleReturn;
    }

    public function parse()
    {
        while ($this->state != 'end') {
            $this->state = 'init';
            $this->InterfaceFuncParseLine();
        }

        $interfaceClass = $this->getFileHeader('').$this->extraUse.'class '.$this->interfaceName.$this->returnSymbol . '{'.$this->returnSymbol;

        $interfaceClass .= $this->getInterfaceBasic();

        $interfaceClass .= $this->funcSet;

        $interfaceClass .= '}'.$this->doubleReturn;

        return [
            'syn' => $interfaceClass,
        ];
    }

    /**
     * @param $fp
     * @param $line
     * 这里必须要引入状态机了
     */
    public function InterfaceFuncParseLine()
    {
        $line = '';
        $this->state = 'init';
        while (1) {
            $char = fgetc($this->fp);

            if ($this->state == 'init') {
                // 有可能是换行
                if ($char == '{' || Utils::isReturn($char)) {
                    continue;
                }
                // 遇到了注释会用贪婪算法全部处理完,同时填充到struct的类里面去
                elseif ($char == '/') {
                    $this->copyAnnotation();
                    break;
                } elseif (Utils::inIdentifier($char)) {
                    $this->state = 'identifier';
                    $line .= $char;
                }
                // 终止条件之1,宣告struct结束
                elseif ($char == '}') {
                    // 需要贪心的读到"\n"为止
                    while (($lastChar = fgetc($this->fp)) != "\n") {
                        continue;
                    }
                    $this->state = 'end';
                    break;
                }
            } elseif ($this->state == 'identifier') {
                if ($char == '/') {
                    $this->copyAnnotation();
                } elseif ($char == ';') {
                    $line .= $char;
                    $this->state = 'lineEnd';
                } elseif (Utils::isReturn($char)) {
                    continue;
                } else {
                    $line .= $char;
                }
            } elseif ($this->state == 'lineEnd') {
                if ($char == '}') {
                    // 需要贪心的读到"\n"为止
                    while (($lastChar = fgetc($this->fp)) != "\n") {
                        continue;
                    }
                    $this->state = 'end';
                }
                break;
            } elseif ($this->state == 'end') {
                break;
            }
        }

        if (empty($line)) {
            return;
        }

        $line = trim($line);

        // 如果空行，或者是注释，或者是大括号就直接略过
        if (!trim($line) || trim($line)[0] === '/' || trim($line)[0] === '*') {
            return;
        }

        $ret = preg_match("/rpc +(\w+)\((\w+)\) +returns +\((\w+)\) +{ *};/", $line, $match);
        if (!$ret) {
            Utils::abnormalExit('error', '匹配正则失败，请检查， 内容：' . $line);
        }

        $funcName = $match[1];
        $params[] = $match[2];
        $params[] = $match[3];

        $this->writeInterfaceLine('void', $funcName, $params);
    }

    public function paramParser($params)
    {
        foreach ($params as $param) {
            // 同时要把它增加到本Interface的依赖中
            $this->extraUse .= 'use '.$this->paramNamespace.'\\'.$param.';'.$this->returnSymbol;
        }

        return [
            'in' => [
                [
                    'type' => $params[0],
                    'wholeType' => '',
                    'valueName' => 'inParam',
                ]
            ],
            'out' => [
                [
                    'type' => $params[1],
                    'wholeType' => '',
                    'valueName' => 'outParam',
                ]
            ],
        ];
    }

    /**
     * @param $tag
     * @param $requireType
     * @param $type
     * @param $name
     * @param $wholeType
     * @param $defaultValue
     */
    public function writeInterfaceLine($returnType, $funcName, $params)
    {
        $result = $this->paramParser($params);
        $inParams = $result['in'];
        $outParams = $result['out'];

        // 处理通用的头部
        $funcHeader = $this->generateFuncHeader($funcName, $inParams, $outParams);

        $funcBodyArr = $this->generateFuncBody($inParams, $outParams, null);
        $synFuncBody = $funcBodyArr['syn'];

        $funcTail = $this->tabSymbol.'}'.$this->doubleReturn;

        $this->funcSet .= $funcHeader.$synFuncBody.$funcTail;
    }

    /**
     * @param $funcName
     * @param $inParams
     * @param $outParams
     *
     * @return string
     */
    public function generateFuncHeader($funcName, $inParams, $outParams)
    {
        $paramsStr = '';
        foreach ($inParams as $param) {
            $paramSuffix = '$'.$param['valueName'];
            $paramsStr .= $param['type'].' '.$paramSuffix.',';
        }

        foreach ($outParams as $param) {
            $paramSuffix = '&$'.$param['valueName'];
            $paramsStr .= $param['type'].' '.$paramSuffix.',';
        }

        $paramsStr = trim($paramsStr, ',');
        $paramsStr .= ')' . $this->returnSymbol . '	{' . $this->returnSymbol;

        $funcHeader = $this->tabSymbol.'public function '.$funcName.'('.$paramsStr;

        return $funcHeader;
    }

    /**
     * @param $funcName
     * @param $inParams
     * @param $outParams
     * 生成函数的包体
     */
    public function generateFuncBody($inParams, $outParams)
    {
        $bodyPrefix = $this->doubleTab.'try {'.$this->returnSymbol;

        $bodySuffix = $this->doubleTab.'catch (\\Exception $e) {'.$this->returnSymbol.
            $this->tripleTab.'throw $e;'.$this->returnSymbol.
            $this->doubleTab.'}'.$this->returnSymbol;

        $bodyMiddle = $this->tripleTab.'$requestPacket = new GrpcRequestPacket();'.$this->returnSymbol.
            $this->tripleTab.'$requestPacket->_funcName = __FUNCTION__;'.$this->returnSymbol.
            $this->tripleTab.'$requestPacket->_servantName = $this->_servantName;'.$this->returnSymbol.
            $this->tripleTab.'$requestPacket->_basePath = $this->_basePath;'.$this->returnSymbol.
            $this->tripleTab.'$requestPacket->_sBuffer = $inParam->serializeToString();'.$this->doubleReturn.

            $this->tripleTab.'$responsePacket = new GrpcResponsePacket();'.$this->returnSymbol.
            $this->tripleTab.'$sBuffer = $this->_communicator->invoke($requestPacket, $this->_iTimeout, $responsePacket);'.$this->doubleReturn.

            $this->tripleTab.'$outParam = new '. $outParams[0]['type'] . '();'.$this->returnSymbol.
            $this->tripleTab.'$outParam->mergeFromString($sBuffer);'.$this->returnSymbol;


        $bodyMiddle .= $this->doubleTab.'}'.$this->returnSymbol;

        $bodyStr = $bodyPrefix.$bodyMiddle.$bodySuffix;

        return [
            'syn' => $bodyStr,
        ];
    }
}

class ServantParser
{
    public $namespaceName;
    public $moduleName;
    public $interfaceName;

    public $state;

    // 这个结构体,可能会引用的部分,包括其他的结构体、枚举类型、常量
    public $useStructs = [];
    public $extraUse;
    public $preStructs;
    public $preEnums;

    public $preNamespaceEnums = [];
    public $preNamespaceStructs = [];

    public $firstLine;

    public $returnSymbol = "\n";
    public $doubleReturn = "\n\n";
    public $tabSymbol = "\t";
    public $doubleTab = "\t\t";
    public $tripleTab = "\t\t\t";
    public $quardupleTab = "\t\t\t\t";

    public $extraContructs = '';
    public $extraExtType = '';
    public $extraExtInit = '';

    public $consts = '';
    public $variables = '';
    public $fields = '';

    public $funcSet = '';

    public $servantName;
    public $paramNamespace;

    public function __construct($fp, $line, $namespaceName, $moduleName,
                                $interfaceName, $preStructs,
                                $preEnums, $servantName, $preNamespaceEnums, $preNamespaceStructs, $paramNamespace)
    {
        $this->fp = $fp;
        $this->firstLine = $line;
        $this->namespaceName = $namespaceName;
        $this->moduleName = $moduleName;
        $this->preStructs = $preStructs;
        $this->preEnums = $preEnums;
        $this->interfaceName = $interfaceName;
        $this->servantName = $servantName;

        $this->extraUse = '';
        $this->useStructs = [];

        $this->preNamespaceEnums = $preNamespaceEnums;
        $this->preNamespaceStructs = $preNamespaceStructs;

        $this->paramNamespace = $paramNamespace;
    }

    public function getFileHeader($prefix = '')
    {
        return "<?php\n\nnamespace ".$this->namespaceName.$prefix.';'. $this->doubleReturn;
    }

    public function parse()
    {
        while ($this->state != 'end') {
            $this->InterfaceFuncParseLine();
        }

        // todo serverName+servant
        $interfaceClass = $this->getFileHeader('').$this->extraUse.'interface '.$this->interfaceName.' {'.$this->returnSymbol;

        $interfaceClass .= $this->funcSet;

        $interfaceClass .= '}'.$this->doubleReturn;

        return $interfaceClass;
    }

    /**
     * @param $startChar
     * @param $lineString
     *
     * @return string
     *                专门处理注释
     */
    public function copyAnnotation()
    {
        // 再读入一个字符
        $nextChar = fgetc($this->fp);
        // 第一种
        if ($nextChar == '/') {
            while (1) {
                $tmpChar = fgetc($this->fp);
                if (Utils::isReturn($tmpChar)) {
                    $this->state = 'lineEnd';
                    break;
                }
            }

            return;
        } elseif ($nextChar == '*') {
            while (1) {
                $tmpChar = fgetc($this->fp);

                if ($tmpChar === false) {
                    Utils::abnormalExit('error', $this->interfaceName.'注释换行错误,请检查');
                } elseif (Utils::isReturn($tmpChar)) {
                } elseif (($tmpChar) === '*') {
                    $nextnextChar = fgetc($this->fp);
                    if ($nextnextChar == '/') {
                        return;
                    } else {
                        $pos = ftell($this->fp);
                        fseek($this->fp, $pos - 1);
                    }
                }
            }
        }
        // 注释不正常
        else {
            Utils::abnormalExit('error', $this->interfaceName.'注释换行错误,请检查');
        }
    }

    /**
     * @param $fp
     * @param $line
     * 这里必须要引入状态机了
     * 这里并不一定要一个line呀,应该找)作为结束符
     */
    public function InterfaceFuncParseLine()
    {
        $line = '';
        $this->state = 'init';
        while (1) {
            $char = fgetc($this->fp);

            if ($this->state == 'init') {
                // 有可能是换行
                if ($char == '{' || Utils::isReturn($char)) {
                    continue;
                }
                // 遇到了注释会用贪婪算法全部处理完,同时填充到struct的类里面去
                elseif ($char == '/') {
                    $this->copyAnnotation();
                    break;
                } elseif (Utils::inIdentifier($char)) {
                    $this->state = 'identifier';
                    $line .= $char;
                }
                // 终止条件之1,宣告struct结束
                elseif ($char == '}') {
                    // 需要贪心的读到"\n"为止
                    while (($lastChar = fgetc($this->fp)) != "\n") {
                        continue;
                    }
                    $this->state = 'end';
                    break;
                }
            } elseif ($this->state == 'identifier') {
                if ($char == '/') {
                    $this->copyAnnotation();
                } elseif ($char == ';') {
                    $line .= $char;
                    $this->state = 'lineEnd';
                } elseif (Utils::isReturn($char)) {
                    continue;
                } else {
                    $line .= $char;
                }
            } elseif ($this->state == 'lineEnd') {
                if ($char == '}') {
                    // 需要贪心的读到"\n"为止
                    while (($lastChar = fgetc($this->fp)) != "\n") {
                        continue;
                    }
                    $this->state = 'end';
                }
                break;
            } elseif ($this->state == 'end') {
                break;
            }
        }

        if (empty($line)) {
            return;
        }

        $line = trim($line);

        // 如果空行，或者是注释，或者是大括号就直接略过
        if (!trim($line) || trim($line)[0] === '/' || trim($line)[0] === '*') {
            return;
        }

        $ret = preg_match("/rpc +(\w+) *\((\w+)\) +returns +\((\w+)\) +{ *};?/", $line, $match);
        if (!$ret) {
            Utils::abnormalExit('error', '匹配正则失败，请检查， 内容：' . $line);
        }

        $funcName = $match[1];
        $params[] = $match[2];
        $params[] = $match[3];

        $this->writeInterfaceLine('void', $funcName, $params);
    }

    public function paramParser($params)
    {
        foreach ($params as $param) {
            // 同时要把它增加到本Interface的依赖中
            $this->extraUse .= 'use '.$this->paramNamespace.'\\'.$param.';'.$this->returnSymbol;
        }

        return [
            'in' => [
                [
                    'type' => $params[0],
                    'wholeType' => '',
                    'valueName' => 'inParam',
                ]
            ],
            'out' => [
                [
                    'type' => $params[1],
                    'wholeType' => '',
                    'valueName' => 'outParam',
                ]
            ],
        ];
    }

    public function writeInterfaceLine($returnType, $funcName, $params)
    {
        $result = $this->paramParser($params);
        $inParams = $result['in'];
        $outParams = $result['out'];

        $funcAnnotation = $this->generateFuncAnnotation($inParams, $outParams, null);

        // 函数定义恰恰是要放在最后面了
        $funcDefinition = $this->generateFuncHeader($funcName, $inParams, $outParams);

        $this->funcSet .= $funcAnnotation.$funcDefinition . $this->returnSymbol;
    }

    /**
     * @param $funcName
     * @param $inParams
     * @param $outParams
     *
     * @return string
     */
    public function generateFuncHeader($funcName, $inParams, $outParams)
    {
        $paramsStr = '';
        foreach ($inParams as $param) {
            $paramSuffix = '$'.$param['valueName'];
            $paramsStr .= $param['type'].' '.$paramSuffix.',';
        }

        foreach ($outParams as $param) {
            $paramSuffix = '&$'.$param['valueName'];
            $paramsStr .= $param['type'].' '.$paramSuffix.',';
        }

        $paramsStr = trim($paramsStr, ',');
        $paramsStr .= ');'.$this->returnSymbol;

        $funcHeader = $this->tabSymbol.'public function '.$funcName.'('.$paramsStr;

        return $funcHeader;
    }

    /**
     * @param $funcName
     * @param $inParams
     * @param $outParams
     * 生成函数的包体
     */
    public function generateFuncAnnotation($inParams, $outParams, $returnInfo)
    {
        $bodyPrefix = $this->tabSymbol.'/**'.$this->returnSymbol;

        $bodyMiddle = '';

        foreach ($inParams as $param) {

            $annotation = $this->tabSymbol.' * @param ';
            $type = $param['type'];
            $valueName = $param['valueName'];

            $annotation .= '\\' . $this->paramNamespace . '\\' . $type . ' $' . $valueName;
            $bodyMiddle .= $annotation.$this->returnSymbol;
        }

        foreach ($outParams as $param) {
            $annotation = $this->tabSymbol.' * @param ';
            $type = $param['type'];
            $valueName = $param['valueName'];

            $annotation .= '\\' . $this->paramNamespace . '\\' . $type . ' $' . $valueName;
            $annotation .= ' =out='.$this->returnSymbol;
            $bodyMiddle .= $annotation;
        }

        // 还要尝试去获取一下接口的返回码哦 总是void
        $annotation = $this->tabSymbol.' * @return ';
        $annotation .= 'void';
        $bodyMiddle .= $annotation.$this->returnSymbol.$this->tabSymbol.' */'.$this->returnSymbol;

        $bodyStr = $bodyPrefix.$bodyMiddle;

        return  $bodyStr;
    }
}
