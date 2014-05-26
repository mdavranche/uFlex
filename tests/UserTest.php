<?php
/**
 * Created by PhpStorm.
 * User: Pablo
 * Date: 3/29/14
 * Time: 11:23 PM
 */

namespace tests;


use Ptejada\UFlex\User;

class UserTest extends \PHPUnit_Framework_TestCase {
    /** @var User  */
    public $user;

    public function setUp()
    {
        // Instantiate the global session variable
        $_SESSION = array();
        // Instantiate the global cookie variable
        $_COOKIE = array();

        $this->user = new User();
        $this->user->config->database->dsn = 'sqlite::memory:';

        $this->user->start();

        // Creates the table
        $this->user->table->runQuery("
            CREATE TABLE IF NOT EXISTS _table_ (
              `ID` int(7),
              `Username` varchar(15) NOT NULL,
              `Password` char(40) ,
              `Email` varchar(35) ,
              `Activated` tinyint(1) NOT NULL DEFAULT '0',
              `Confirmation` char(40),
              `RegDate` int(11) ,
              `LastLogin` int(11) NOT NULL DEFAULT '0',
              PRIMARY KEY (`ID`)
            )
        ");

        //Create user
        $this->user->table->runQuery('
            INSERT INTO _table_(`ID`, `Username`, `Password`, `Email`, `Activated`, `RegDate`)
            VALUES (1,"pablo","18609a032b2504973748587e8c428334","pablo@live.com",1,1361145707)
        ');
    }

    public function testDefaultInitialization()
    {
        $this->user->login();
        $this->assertFalse($this->user->isSigned());
    }

    public function testLoginFromSession()
    {
        $_SESSION['userData'] = array(
            'data' => array(
                'ID' => 1,
            ),
            'update' => true,
            'signed' => true,
        );

        $this->user->login();

        $this->assertFalse($this->user->log->hasError());
        $this->assertTrue($this->user->isSigned());

        $this->assertGreaterThanOrEqual(5, count($this->user->session->data->toArray()));
        $this->assertNotEmpty($this->user->Username);
        $this->assertNotEmpty($this->user->Password);
        $this->assertNotEmpty($this->user->Email);

        $this->assertTrue($this->user->isSigned());
    }

    public function testLoginFromCookies()
    {
        $_COOKIE['auto'] = '130118609a032b973748587e8c42833465498745';

        ob_start();
        $this->user->login();
        $output = ob_get_contents();
        ob_end_clean();

        $this->assertFalse($this->user->log->hasError());

        // Expect the autologin cookie to be set
        $this->assertNotEmpty($output);
        $this->assertEquals(0, strpos($output, '<script>'));

        $this->assertGreaterThanOrEqual(5, count($this->user->session->data->toArray()));
        $this->assertNotEmpty($this->user->Username);
        $this->assertNotEmpty($this->user->Password);
        $this->assertNotEmpty($this->user->Email);

        $this->assertTrue($this->user->isSigned());
    }

    public function testLoginWithCredentials()
    {
        $this->user->login();

        $this->assertFalse($this->user->log->hasError());

        $this->user->login('pablo', 123);
        $this->assertTrue($this->user->log->hasError());

        $this->user->login('pablo', 1234);
        $this->assertFalse($this->user->log->hasError());
    }

    public function testFieldValidation()
    {
        $userInfo = $this->getUserInfo();

        $userInfo['Username'] = md5(time());
        $success = $this->user->register($userInfo);
        $this->assertEquals($success, !$this->user->log->hasError());
        $this->assertFalse($success);

        $userInfo['Username'] = 'user 1';
        $success = $this->user->register($userInfo);
        $this->assertEquals($success, !$this->user->log->hasError());
        $this->assertFalse($success);

        $userInfo['Username'] = 'u1';
        $success = $this->user->register($userInfo);
        $this->assertEquals($success, !$this->user->log->hasError());
        $this->assertFalse($success);

        $this->user->addValidation('Username', '2-15');
        $success = $this->user->register($userInfo);
        $this->assertEquals($success, !$this->user->log->hasError());
        $this->assertTrue($success);

        $this->assertEquals(array(), $this->user->log->getErrors());
    }

    public function testRegisterNewAccount()
    {
        $userInfo = $this->getUserInfo(2);

        $success = $this->user->register($userInfo);

        $this->assertEquals($success, !$this->user->log->hasError());
        $this->assertTrue($success);

        $this->assertFalse($this->user->isSigned());
        $this->user->login($userInfo['Username'], $userInfo['Password']);
        $this->assertTrue($this->user->isSigned());

        $this->assertNotEmpty($this->user->Username);
        $this->assertNotEmpty($this->user->Password);
        $this->assertNotEmpty($this->user->Email);
    }

    public function testUserUpdate()
    {
        $this->user->login('pablo', 1234);

        $this->assertFalse($this->user->log->hasError());
        $this->assertEmpty($this->user->session->update);

        $newEmail = 'jose@live.com';
        $this->user->update(array('Email'=>$newEmail));

        $this->assertTrue($this->user->session->update);
        $this->assertEquals($newEmail, $this->user->Email);
        $this->assertNotEmpty($this->user->Username);
    }

    public function testResetPassword()
    {
        $this->user->login();

        $this->assertFalse($this->user->log->hasError());

        $result = $this->user->resetPassword('jose@live.com');
        $this->assertFalse($result);
        $this->assertTrue($this->user->log->hasError());

        $result = $this->user->resetPassword('pablo@live.com');
        $this->assertInstanceOf('Ptejada\UFlex\Collection',$result);
        $this->assertFalse($this->user->log->hasError());

        $this->assertEquals('pablo', $result->Username);
        $this->assertEquals('pablo@live.com', $result->Email);
        $this->assertEquals(1, $result->ID);
        $this->assertEquals(40, strlen($result->Confirmation));

        // Confirm confirmation was saved on the on DB
        $user = $this->user->table->getRow(array('ID'=>1));
        $this->assertEquals($user->Confirmation, $result->Confirmation);
    }

    public function testNewPassword()
    {
        $this->user->login('pablo', 456);
        $this->assertTrue($this->user->log->hasError());
        $this->assertFalse($this->user->isSigned());

        $result = $this->user->resetPassword('pablo@live.com');
        $this->assertInstanceOf('Ptejada\UFlex\Collection',$result);
        $this->assertFalse($this->user->log->hasError());

        $newPassword = array(
            'Password' => 456,
            'Password2' => 789,
        );

        $this->user->newPassword($result->Confirmation, $newPassword);
        $this->assertTrue($this->user->log->hasError());

        $newPassword['Password2'] = 456;
        $this->user->newPassword('c4504f0b39c478f39c4badbf74ddbaedf71ecfae' , $newPassword);
        $this->assertTrue($this->user->log->hasError());

        $this->user->newPassword($result->Confirmation, $newPassword);
        $this->assertFalse($this->user->log->hasError());

        // Test the new login credentials
        $this->user->login('pablo', 456);
        $this->assertFalse($this->user->log->hasError());
        $this->assertTrue($this->user->isSigned());
    }

    public function testLogout()
    {
        $this->user->login('pablo', 1234);
        $this->assertFalse($this->user->log->hasError());
        $this->assertTrue($this->user->isSigned());

        $this->user->logout();
        $this->assertFalse($this->user->isSigned());
    }

    public function testManageUser()
    {
        $this->user->register($this->getUserInfo(5));

        $this->user->login('pablo', 1234);
        $this->assertFalse($this->user->log->hasError());
        $this->assertTrue($this->user->isSigned());

        $user = $this->user->manageUser(5);
        $this->assertInstanceOf('\Ptejada\UFlex\User', $user);

        $this->assertNotEquals('jose', $user->Username);
        $result = $user->update(array('Username'=>'jose'));
        $this->assertTrue($result);
        $this->assertEquals('jose', $user->Username);

        // Reload the user from the DB to confirm update
        $user = $this->user->manageUser(5);
        $this->assertEquals('jose', $user->Username);

        // confirm the main user was not affected
        $this->assertEquals('pablo', $this->user->Username);
    }

    protected function getUserInfo($id=0)
    {
        return array(
            'ID' => $id ? $id : rand(),
            'Username' => 'user' . rand(),
            'Password' => substr(md5(rand()), 0, 7),
            'Email'   => substr(md5(rand()), 0, 5) . '@' . substr(md5(rand()), 0, 8) . '.com',
        );
    }

    protected function tearDown()
    {
        $this->user = null;
    }


}
 