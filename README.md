# Instructions for using tars2php



Brief introduction

The main function of tars2php is to automatically generate the PHP code of client side and server side through the tars protocol file, which is used by everyone. (the server side is mainly framework code, and the actual business logic needs to be supplemented and implemented by itself)



## Mapping of basic types

Here is our mapping of basic types:
```
    bool => \TARS::BOOL
    char => \TARS::CHAR
    uint8 => \TARS::UINT8
    short => \TARS::SHORT
    uint16 => \TARS::UINT16
    float => \TARS::FLOAT
    double => \TARS::DOUBLE
    int32 => \TARS::INT32
    uint32 => \TARS::UINT32
    int64 => \TARS::INT64
    string => \TARS::STRING
    vector => \TARS::VECTOR
    map => \TARS::MAP
    struct => \TARS::STRUCT
```
When we need to identify specific variable types, we need to use these basic types, which are constants, from 1-14.



##Mapping of complex types

There are some special packaging and unpacking mechanisms for vector, map and struct. Therefore, special data types need to be introduced:

Vector:
```
    vector => \TARS_VECTOR
    It has two member functions, push back() and push back()

The input parameter depends on what type of array the vector itself contains



For example:
    $shorts = ["test1","test2"];
    $vector = new \TARS_VECTOR(\TARS::STRING); //定义一个string类型的vector
    foreach ($shorts as $short) {
        $vector->pushBack($short); //依次吧test1，test2两个元素，压入vector中
    }
```
map:
```
    map => \TARS_MAP
   It has two member functions, push back() and push back()

The input parameter depends on what type the map itself contains



For example:
    $strings = [["test1"=>1],["test2"=>2]];
    $map = new \TARS_MAP(\TARS::STRING,\TARS::INT64); //定义一个key为string,value是int64的map
    foreach ($strings as $string) {
        $map->pushBack($string); //Press two elements into the map in turn, and notice that pushback receives an array, and that array has only one element
    }
```

struct:
```
    struct => \TARS_Struct
    sThe constructor of struct is special. It takes two parameters, classname and classfields

The first describes the name, and the second describes the information of the variables in struct



For example:
	class SimpleStruct extends \TARS_Struct {
		const ID = 0; //TARS file every struct的tag
		const COUNT = 1;

		public $id; //strcutThe values of each element in are saved here
		public $count; 

		protected static $fields = array(
			self::ID => array(
				'name'=>'id',//struct every element 
				'required'=>true,//Whether each element in struct is required or not corresponds to the requirements and optional in the tars file
				'type'=>\TARS::INT64,//struct every element type
				),
			self::COUNT => array(
				'name'=>'count',
				'required'=>true,
				'type'=>\TARS::UINT8,
				),
		);

		public function __construct() {
			parent::__construct('App_Server_Servant.SimpleStruct', self::$fields);
		}
	}
```

## How to use tars2php

If the user only uses packaging and unpacking requirements, the process is as follows:



0. Prepare a tar protocol file, such as example.tar



1. Write a tar.proto.php file, which is the configuration file used to generate code.

```

//The service name of this example is phptest.phpserver.obj
return array(
‘appName’=>‘PHPTest’，//tars name

“server name”=>“PHPServer”，//tars servant name

‘obj name’=>‘obj’，//tars slaver name

“with servant”=>correct，//

'tarsFiles'=>array(

'./example.tars'//tars

),

'dstPath'=>'./server/'，//php

'namespace prefix' = > 'server / service'，//php'

);
```
2. exec php ./tars2php.php ./tars.proto.php

3. The tool will automatically generate three-level directory structure according to the service name. In the demo, the phptest / PHP server / obj / directory will be generated under the. / server directory. The classes under the obj directory are the PHP objects corresponding to struct, and the tar directory is the tar protocol file itself.

For example, struct in example.tar:
```
struct SimpleStruct {
    0 require long id=0;
    1 require unsigned int count=0;
    2 require short page=0;
};
```
转变成classes/SimpleStruct.php

```
<?php

namespace Server\servant\PHPTest\PHPServer\obj\classes;

class SimpleStruct extends \TARS_Struct {
	const ID = 0; //tars protocal tag
	const COUNT = 1;
	const PAGE = 2;
	
	public $id; //Actual value of element
	public $count; 
	public $page; 
	
	protected static $_fields = array(
		self::ID => array(
			'name'=>'id', //tars Name of no element in the agreement
			'required'=>true, //tars Required or optional in the agreement
			'type'=>\TARS::INT64, //type
			),
		self::COUNT => array(
			'name'=>'count',
			'required'=>true,
			'type'=>\TARS::UINT32,
			),
		self::PAGE => array(
			'name'=>'page',
			'required'=>true,
			'type'=>\TARS::SHORT,
			),
	);

	public function __construct() {
		parent::__construct('PHPTest_PHPServer_obj_SimpleStruct', self::$_fields);
	}
}
```

4. The interface section in example. Tar automatically generates a separate interface named PHP file.

For example, 'int testlofoftags (lotoftags tags, out lotoftags outbound);' the interface generation method is as follows



Part server

```

<? PHP

//Note that the comment part of the generated file will be converted to PHP code when the server is started. If not necessary, do not modify it

//The specific implementation of the server part needs to be inherited by itself. The notes are as follows



//The parameter is of struct type, corresponding to $tags variable. The corresponding PHP object is in \ server \ service \ phptest \ phpserver \ obj \ classes \ lotoftags

//The parameter is of struct type, corresponding to $outputs variable. The corresponding PHP object is in \ server \ service \ phptest \ phpserver \ obj \ classes \ lotoftags, which is the output parameter

//Interface prevent to int
	/**
	 * @param struct $tags \Server\servant\PHPTest\PHPServer\obj\classes\LotofTags
	 * @param struct $outtags \Server\servant\PHPTest\PHPServer\obj\classes\LotofTags =out=
	 * @return int 
	 */
	public function testLofofTags(LotofTags $tags,LotofTags &$outtags);
```
client part 
```
<?php
	try {
		$requestPacket = new RequestPacket(); //Parameters required to build the request package
		$requestPacket->_iVersion = $this->_iVersion;
		$requestPacket->_funcName = __FUNCTION__;
		$requestPacket->_servantName = $this->_servantName;
		
		$encodeBufs = [];

		$__buffer = TUPAPIWrapper::putStruct("tags",1,$tags,$this->_iVersion); //Package first parameter tags
		$encodeBufs['tags'] = $__buffer;
		
		$requestPacket->_encodeBufs = $encodeBufs; //Set request in request package bufs

		$sBuffer = $this->_communicator->invoke($requestPacket,$this->_iTimeout); //Send request package, receive return package

		$ret = TUPAPIWrapper::getStruct("outtags",2,$outtags,$sBuffer,$this->_iVersion); //Extract the first output parameter from the return packageouttags
		return TUPAPIWrapper::getInt32("",0,$sBuffer,$this->_iVersion); //The return parameter name is empty and the tag is 0

	}
	catch (\Exception $e) {
		throw $e;
	}
```