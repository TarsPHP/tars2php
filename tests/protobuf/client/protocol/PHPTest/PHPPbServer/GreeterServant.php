<?php

namespace Protocol\PHPTest\PHPPbServer;

use Tars\client\CommunicatorConfig;
use Tars\client\Communicator;
use Tars\client\grpc\GrpcRequestPacket;
use Tars\client\grpc\GrpcResponsePacket;
use Helloworld\HelloRequest;
use Helloworld\HelloReply;
class GreeterServant
{
	protected $_communicator;
	protected $_iTimeout;
	public $_servantName = "PHPTest.PHPPbServer.obj";
	public $_basePath = "/helloworld.Greeter/";

	public function __construct(CommunicatorConfig $config) {
		try {
			$config->setServantName($this->_servantName);
			$this->_communicator = new Communicator($config);
			$this->_iTimeout = empty($config->getAsyncInvokeTimeout())?2:$config->getAsyncInvokeTimeout();
		} catch (\Exception $e) {
			throw $e;
		}
	}

	public function SayHello(HelloRequest $inParam,HelloReply &$outParam)
	{
		try {
			$requestPacket = new GrpcRequestPacket();
			$requestPacket->_funcName = __FUNCTION__;
			$requestPacket->_servantName = $this->_servantName;
			$requestPacket->_basePath = $this->_basePath;
			$requestPacket->_sBuffer = $inParam->serializeToString();

			$responsePacket = new GrpcResponsePacket();
			$sBuffer = $this->_communicator->invoke($requestPacket, $this->_iTimeout, $responsePacket);

			$outParam = new HelloReply();
			$outParam->mergeFromString($sBuffer);
		}
		catch (\Exception $e) {
			throw $e;
		}
	}

}

