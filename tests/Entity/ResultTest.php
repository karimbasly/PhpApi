<?php

/**
 * @category TestEntities
 * @package  App\Tests\Entity
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     http://www.etsisi.upm.es/ E.T.S. de Ingeniería de Sistemas Informáticos
 */

namespace App\Tests\Entity;

use App\Entity\Result;
use App\Entity\User;
use Exception;
use Faker\Factory as FakerFactoryAlias;
use Faker\Generator as FakerGeneratorAlias;
use Faker\Provider\DateTime as FakerDateTime;
use PHPUnit\Framework\TestCase;


/**
 * Class ResultTest
 *
 * @package App\Tests\Entity
 *
 * @group   entities
 * @coversDefaultClass \App\Entity\Result
 */
class ResultTest extends TestCase
{
    protected static Result $result;
    protected static User $user;
    protected static \DateTime $timestamp;

    private static FakerGeneratorAlias $faker;

    /**
     * Sets up the fixture.
     * This method is called before a test is executed.
     */
    public static function setUpBeforeClass(): void
    {
        self::$timestamp =  new \DateTime('now');
        self::$user = new User();
        self::$result = new Result(0, self::$user, self::$timestamp);
        self::$faker = FakerFactoryAlias::create('es_ES');

    }



    /**
     * Implement testConstructor().
     *
     * @return void
     */
    public function testConstructor(): void
    {
        self::$timestamp = new \DateTime('now');
        self::$user = new User();
        self::$result = new Result(0, self::$user, self::$timestamp);

        self::assertEquals(0, self::$result->getResult());
        self::assertEmpty(self::$user->getUserIdentifier());
        self::assertEmpty(self::$user->getEmail());
        self::assertSame(0, self::$user->getId());
        self::assertEqualsWithDelta(new \DateTime('now'), self::$result->getTime(), 1);
    }
    /**
     * Implement testGetId().
     *
     * @return void
     */
    public function testGetId(): void
    {
        self::assertSame(0, self::$result->getId());
    }


    /**
     * Implements testGetSetResult().
     * Nota: El rango seleccionado pretende adecuarse al juego "Bantumi".
     *
     * @throws Exception
     * @return void
     */
    public function testGetSetResult(): void
    {
        $userResult = self::$faker->numberBetween(0, 40);
        self::$result->setResult($userResult);
        static::assertSame(
            $userResult,
            self::$result->getResult()
        );
    }
    /**
     * Implements testGetSetUser().
     *
     * @throws Exception
     * @return void
     */
    public function testGetSetUser(): void
    {  $password=self::$faker->password();
        $email= self::$faker->email();
        $testUser = new User($email, $password);
        self::$result->setUser($testUser);
        static::assertSame($testUser, self::$result->getUser());
    }
    /**
     * Implements testGetSetTime().
     *
     * @throws Exception
     * @return void
     */
    public function testGetSetTime(): void
    {
        $resultTimestamp = FakerDateTime::dateTime();
        self::$result->setTime($resultTimestamp);
        static::assertSame($resultTimestamp, self::$result->getTime());
    }

}
