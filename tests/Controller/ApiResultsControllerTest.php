<?php

namespace App\Tests\Controller;

use App\Entity\Result;
use App\Entity\User;
use DateTime;
use Faker\Factory as FakerFactoryAlias;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ApiResultsControllerTest
 *
 * @package App\Tests\Controller
 * @group   controllers
 *
 * @coversDefaultClass \App\Controller\ApiResultsController
 */
class ApiResultsControllerTest extends BaseTestCase
{
    private const RUTA_API = '/api/v1/results';

    /** @var array<string,string> $adminHeaders */
    private static array $adminHeaders;

    /**
     * Test OPTIONS /results[/resultId] 204 No Content
     *
     * @covers ::__construct
     * @covers ::optionsAction
     * @return void
     */
    public function testOptionsResultAction204NoContent(): void
    {
        // OPTIONS /api/v1/results
        self::$client->request(
            Request::METHOD_OPTIONS,
            self::RUTA_API
        );
        $response = self::$client->getResponse();

        self::assertSame(
            Response::HTTP_NO_CONTENT,
            $response->getStatusCode()
        );
        self::assertNotEmpty($response->headers->get('Allow'));

        // OPTIONS /api/v1/results/{id}
        self::$client->request(
            Request::METHOD_OPTIONS,
            self::RUTA_API . '/' . self::$faker->numberBetween(1, 100)
        );

        self::assertSame(
            Response::HTTP_NO_CONTENT,
            $response->getStatusCode()
        );
        self::assertNotEmpty($response->headers->get('Allow'));
    }

    /**
     * Test POST /results 201 Created
     *
     * @return array<Result> result data
     */
    public function testPostResultAction201Created(): array
    {
        $p_data2 = [
            User::EMAIL_ATTR => self::$faker->email(),
            User::PASSWD_ATTR => self::$faker->password(),
            User::ROLES_ATTR => [ self::$faker->word() ],
        ];
        $p_data = [
            Result::RESULTA_ATTR => self::$faker->numberBetween(0,20),
            Result::USER_ATTR=>$p_data2,
        ];
        self::$adminHeaders = $this->getTokenHeaders(
            self::$role_admin[User::EMAIL_ATTR],
            self::$role_admin[User::PASSWD_ATTR]
        );

        // 201
        self::$client->request(
            Request::METHOD_POST,
            self::RUTA_API,
            [],
            [],
            self::$adminHeaders,
            strval(json_encode($p_data))
        );
        $response = self::$client->getResponse();
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertTrue($response->isSuccessful());
        self::assertNotNull($response->headers->get('Location'));
        self::assertJson(strval($response->getContent()));
        $result = json_decode(strval($response->getContent()), true);
        self::assertNotEmpty($result['results']['id']);
        self::assertSame($p_data[Result::RESULTA_ATTR], $result['results'][Result::RESULTA_ATTR]);


        return $result['results'];
    }

    /**
     * Test GET /results 200 Ok
     *
     * @depends testPostResultAction201Created
     *
     * @return string ETag header
     */
    public function testCGetResultAction200Ok(): string
    {
        self::$client->request(Request::METHOD_GET, self::RUTA_API, [], [], self::$adminHeaders);
        $response = self::$client->getResponse();
        self::assertTrue($response->isSuccessful());
        self::assertNotNull($response->getEtag());
        $r_body = strval($response->getContent());
        self::assertJson(strval($r_body));
        $results = json_decode($r_body, true);
        self::assertArrayHasKey('result', $results);

        return (string) $response->getEtag();
    }

    /**
     * Test GET /results 304 NOT MODIFIED
     *
     * @param string $etag returned by testCGetResultAction200Ok
     *
     * @depends testCGetResultAction200Ok
     */
    public function testCGetResultAction304NotModified(string $etag): void
    {
        $headers = array_merge(
            self::$adminHeaders,
            [ 'HTTP_If-None-Match' => [$etag] ]
        );
        self::$client->request(Request::METHOD_GET, self::RUTA_API, [], [], $headers);
        $response = self::$client->getResponse();
        self::assertSame(Response::HTTP_NOT_MODIFIED, $response->getStatusCode());
    }

    /**
     * Test GET /results 200 Ok (with XML header)
     *
     * @param   array<Result> $result result returned by testPostResultAction201()
     * @return  void
     * @depends testPostResultAction201Created
     */
    public function testCGetResultAction200XmlOk(array $result): void
    {
        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/' . $result['id'],
            [],
            [],
            array_merge(
                self::$adminHeaders,
                [ 'HTTP_ACCEPT' => 'application/xml' ]
            )
        );
        $response = self::$client->getResponse();
        self::assertTrue($response->isSuccessful(), strval($response->getContent()));
        self::assertNotNull($response->getEtag());
        self::assertTrue($response->headers->contains('content-type', 'application/xml'));
    }

    /**
     * Test GET /results/{resultId} 200 Ok
     *
     * @param   array<Result> $result result returned by testPostResultAction201()
     * @return  string ETag header
     * @depends testPostResultAction201Created
     */
    public function testGetResultAction200Ok(array $result): string
    {
        self::$client->request(
            Request::METHOD_GET,
            self::RUTA_API . '/' . $result['id'],
            [],
            [],
            self::$adminHeaders
        );
        $response = self::$client->getResponse();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertNotNull($response->getEtag());
        $r_body = (string) $response->getContent();
        self::assertJson($r_body);
        $user_aux = json_decode($r_body, true);
        self::assertSame($result['id'], $user_aux['results']['id']);

        return (string) $response->getEtag();
    }

    /**
     * Test GET /results/{resultId} 304 NOT MODIFIED
     *
     * @param array<Result> $result result returned by testPostResultAction201Created()
     * @param string $etag returned by estGetResultAction200Ok
     * @return string Entity Tag
     *
     * @depends testPostResultAction201Created
     * @depends testGetResultAction200Ok
     */
    public function testGetResultAction304NotModified(array $result, string $etag): string
    {
        $headers = array_merge(
            self::$adminHeaders,
            [ 'HTTP_If-None-Match' => [$etag] ]
        );
        self::$client->request(Request::METHOD_GET, self::RUTA_API . '/' . $result['id'], [], [], $headers);
        $response = self::$client->getResponse();
        self::assertSame(Response::HTTP_NOT_MODIFIED, $response->getStatusCode());

        return $etag;
    }

    /**
     * Test POST /Result 400 Bad Request
     *
     * @param   array<Result> $result result returned by testPostResultAction201Created()
     * @return  array<Result> result data
     * @depends testPostResultAction201Created
     */
    public function testPostResultAction400BadRequest(array $result): array
    {
        $p_data2 = [
            User::EMAIL_ATTR => self::$faker->email(),
            User::PASSWD_ATTR => self::$faker->password(),
        ];
        $p_data = [
            Result::RESULTA_ATTR => self::$faker->numberBetween(0,20),
            Result::USER_ATTR=>$p_data2,
        ];
        self::$client->request(
            Request::METHOD_POST,
            self::RUTA_API,
            [],
            [],
            self::$adminHeaders,
            strval(json_encode($p_data))
        );
        $response = self::$client->getResponse();
        $this->checkResponseErrorMessage($response, Response::HTTP_BAD_REQUEST);

        return $result;
    }

    /**
     * Test PUT /results/{resultId} 209 Content Returned
     *
     * @param   array<Result> $result result returned by testPostResultAction201()
     * @param   string $etag returned by testGetResultAction304NotModified()
     * @return  array<Result> modified result data
     * @depends testPostResultAction201Created
     * @depends testGetResultAction304NotModified
     * @depends testCGetResultAction304NotModified
     * @depends testPostResultAction400BadRequest
     */
    public function testPutResultAction209ContentReturned(array $result, string $etag): array
    {
        $role = self::$faker->word();
        $p_data2 = [
            User::EMAIL_ATTR => self::$faker->email(),
            User::PASSWD_ATTR => self::$faker->password(),
            User::ROLES_ATTR => [ $role ],
        ];
        $p_data = [
            Result::RESULTA_ATTR => self::$faker->numberBetween(0,20),
            Result::USER_ATTR=>$p_data2,
        ];

        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . $result['id'],
            [],
            [],
            array_merge(
                self::$adminHeaders,
                [ 'HTTP_If-Match' => $etag ]
            ),
            strval(json_encode($p_data))
        );
        $response = self::$client->getResponse();

        self::assertSame(209, $response->getStatusCode());
        $r_body = (string) $response->getContent();
        self::assertJson($r_body);
        $result_aux = json_decode($r_body, true);
        self::assertSame($result['id'], $result_aux['results']['id']);
        self::assertSame($result[Result::USER_ATTR], $result_aux['results'][Result::USER_ATTR]);

        return $result_aux['results'];
    }


    /**
     * Test PUT /results/{resultId} 403 FORBIDDEN
     *
     * @param   array<Result> $result result returned by testPutResultAction209ContentReturned()
     * @return  void
     * @depends testPutResultAction209ContentReturned
     * @return void
     * @uses \App\EventListener\ExceptionListener
     */
    public function testPutResultStatus403Forbidden(array $result): void
    {

        $role = self::$faker->word();
        $p_data2 = [
            User::EMAIL_ATTR => self::$faker->email(),
            User::PASSWD_ATTR => self::$faker->password(),
            User::ROLES_ATTR => [ $role ],
        ];
        $p_data = [
            Result::RESULTA_ATTR => self::$faker->numberBetween(0,20),
            Result::USER_ATTR=>$p_data2,
        ];
        $userHeaders = $this->getTokenHeaders(
            self::$role_user[User::EMAIL_ATTR],
            self::$role_user[User::PASSWD_ATTR]
        );

        self::$client->request(
            Request::METHOD_HEAD,
            self::RUTA_API . '/' . $result['id'],
            [],
            [],
            $userHeaders
        );
        $etag = self::$client->getResponse()->getEtag();
        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . $result['id'],
            [],
            [],
            array_merge(
                $userHeaders,
                [ 'HTTP_If-Match' => $etag ]
            ),
            strval(json_encode($p_data))
        );

        $response = self::$client->getResponse();
        $this->checkResponseErrorMessage($response, Response::HTTP_FORBIDDEN);
    }


    /**
     * Test DELETE /results/{resultId} 403 FORBIDDEN
     *
     * @param   array<Result> $result result returned by  testPutResultAction209ContentReturned()
     * @return  void
     * @depends  testPutResultAction209ContentReturned
     * @return void
     * @uses \App\EventListener\ExceptionListener
     */
    public function testDeleteResultStatus403Forbidden(array $result): void
    {

        $userHeaders = $this->getTokenHeaders(
            self::$role_user[User::EMAIL_ATTR],
            self::$role_user[User::PASSWD_ATTR]
        );
        self::$client->request(
            Request::METHOD_DELETE,
            self::RUTA_API . '/' . $result['id'],
            [],
            [],
            $userHeaders
        );
        $response = self::$client->getResponse();
        $this->checkResponseErrorMessage($response, Response::HTTP_FORBIDDEN);
    }

    /**
     * Test PUT /results/{resultId} 400 Bad Request
     *
     * @param   array<Result> $result result returned by testPutResultAction209()
     * @return  void
     * @depends testPutResultAction209ContentReturned
     */
    public function testPutResultAction400BadRequest(array $result): void
    {
        // user does not exists
        $p_data2 = [
            User::EMAIL_ATTR => self::$faker->email(),
            User::PASSWD_ATTR => self::$faker->password(),
        ];
        $p_data = [
            Result::RESULTA_ATTR => self::$faker->numberBetween(0,20),
            Result::USER_ATTR=>$p_data2,
        ];
        self::$client->request(
            Request::METHOD_HEAD,
            self::RUTA_API . '/' . $result['id'],
            [],
            [],
            self::$adminHeaders
        );
        $etag = self::$client->getResponse()->getEtag();
        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . $result['id'],
            [],
            [],
            array_merge(
                self::$adminHeaders,
                [ 'HTTP_If-Match' => $etag ]
            ),
            strval(json_encode($p_data))
        );
        $response = self::$client->getResponse();
        $this->checkResponseErrorMessage($response, Response::HTTP_BAD_REQUEST);
    }

    /**
     * Test PUT /results/{resultId} 412 PRECONDITION_FAILED
     *
     * @param   array<Result> $result result returned by testPutResultAction209ContentReturned()
     * @return  void
     * @depends testPutResultAction209ContentReturned
     */
    public function testPutResultAction412PreconditionFailed(array $result): void
    {
        self::$client->request(
            Request::METHOD_PUT,
            self::RUTA_API . '/' . $result['id'],
            [],
            [],
            self::$adminHeaders
        );
        $response = self::$client->getResponse();
        $this->checkResponseErrorMessage($response, Response::HTTP_PRECONDITION_FAILED);
    }

    /**
     * Test DELETE /results/{resultId} 204 No Content
     *
     * @param   array<Result> $result result returned by testPutResultAction400BadRequest()
     * @return  int resultId
     * @depends testPostResultAction201Created
     * @depends testPutResultAction400BadRequest
     * @depends testPutResultAction412PreconditionFailed
     * @depends testCGetResultAction200XmlOk
     */
    public function testDeleteResultAction204NoContent(array $result): int
    {
        self::$client->request(
            Request::METHOD_DELETE,
            self::RUTA_API . '/' . $result['id'],
            [],
            [],
            self::$adminHeaders
        );
        $response = self::$client->getResponse();

        self::assertSame(
            Response::HTTP_NO_CONTENT,
            $response->getStatusCode()
        );
        self::assertEmpty($response->getContent());

        return intval($result['id']);
    }

    /**
     * Test POST /results 422 Unprocessable Entity
     *
     * @param int|null $result
     * @param User|null $user
     * @param DateTime|null $time time
     * @dataProvider resultProvider422
     * @return void
     * @depends testPutResultAction209ContentReturned
     */
    public function testPostResultAction422UnprocessableEntity(?int $result, ?User $user,?DateTime $time): void
    {
        $p_data = [
            Result::USER_ATTR => $user,
            Result::RESULTA_ATTR=>$result,
            Result::TIME_ATTR=>$time

        ];

        self::$client->request(
            Request::METHOD_POST,
            self::RUTA_API,
            [],
            [],
            self::$adminHeaders,
            strval(json_encode($p_data))
        );
        $response = self::$client->getResponse();
        $this->checkResponseErrorMessage($response, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Test GET    /results 401 UNAUTHORIZED
     * Test POST   /results 401 UNAUTHORIZED
     * Test GET    /results/{resultId} 401 UNAUTHORIZED
     * Test PUT    /results/{resultId} 401 UNAUTHORIZED
     * Test DELETE /results/{resultId} 401 UNAUTHORIZED
     *
     * @param string $method
     * @param string $uri
     * @dataProvider providerRoutes401()
     * @return void
     * @uses \App\EventListener\ExceptionListener
     */
    public function testResultStatus401Unauthorized(string $method, string $uri): void
    {
        self::$client->request(
            $method,
            $uri,
            [],
            [],
            [ 'HTTP_ACCEPT' => 'application/json' ]
        );
        $response = self::$client->getResponse();
        $this->checkResponseErrorMessage($response, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Test GET    /results/{resultId} 404 NOT FOUND
     * Test PUT    /results/{resultId} 404 NOT FOUND
     * Test DELETE /results/{resultId} 404 NOT FOUND
     *
     * @param string $method
     * @param int $resultId result id. returned by testDeleteResultAction204()
     * @return void
     * @dataProvider providerRoutes404
     * @depends      testDeleteResultAction204NoContent
     */
    public function testResultStatus404NotFound(string $method, int $resultId): void
    {
        self::$client->request(
            $method,
            self::RUTA_API . '/' . $resultId,
            [],
            [],
            self::$adminHeaders
        );
        $response = self::$client->getResponse();
        $this->checkResponseErrorMessage($response, Response::HTTP_NOT_FOUND);
    }

    /**
     * Test POST   /results 403 FORBIDDEN
     * @return void
     * @uses \App\EventListener\ExceptionListener
     */
    public function testPostResultStatus403Forbidden(): void
    {
        $p_data2 = [
            User::EMAIL_ATTR => self::$faker->email(),
            User::PASSWD_ATTR => self::$faker->password(),

        ];
        $p_data = [
            Result::RESULTA_ATTR => self::$faker->numberBetween(0,20),
            Result::USER_ATTR=>$p_data2
        ];
        $userHeaders = $this->getTokenHeaders(
            self::$role_user[User::EMAIL_ATTR],
            self::$role_user[User::PASSWD_ATTR]
        );
        self::$client->request(
            Request::METHOD_POST,
            self::RUTA_API,
            [],
            [],
            $userHeaders,
            strval(json_encode($p_data))
        );
        $response = self::$client->getResponse();
        $this->checkResponseErrorMessage($response, Response::HTTP_FORBIDDEN);
    }





    /**
     * * * * * * * * * *
     * P R O V I D E R S
     * * * * * * * * * *
     */

    /**
     * Result provider (incomplete) -> 422 status code
     *
     * @return array<string,mixed> result data
     */
    public function resultProvider422(): array

    { $faker = FakerFactoryAlias::create('es_ES');

        $result = (int) $faker->numberBetween(1,100);
        $email = (string) $faker->email();
        $password = (string) $faker->password();
        $testUser = new User($email, $password);

        return [
            'no_result'  => [ null,$testUser,null ],
            'no_user' => [ $result, null,null      ],
            'nothing'   => [ null,   null,null      ],
        ];
    }

    /**
     * Route provider (expected status: 401 UNAUTHORIZED)
     *
     * @return array<string,mixed> [ method, url ]
     */
    public function providerRoutes401(): array
    {
        return [
            'cgetAction401'   => [ Request::METHOD_GET,    self::RUTA_API ],
            'getAction401'    => [ Request::METHOD_GET,    self::RUTA_API . '/1' ],
            'postAction401'   => [ Request::METHOD_POST,   self::RUTA_API ],
            'putAction401'    => [ Request::METHOD_PUT,    self::RUTA_API . '/1' ],
            'deleteAction401' => [ Request::METHOD_DELETE, self::RUTA_API . '/1' ],
        ];
    }

    /**
     * Route provider (expected status 404 NOT FOUND)
     *
     * @return array<string,mixed> [ method ]
     */
    public function providerRoutes404(): array
    {
        return [
            'getAction404'    => [ Request::METHOD_GET ],
            'putAction404'    => [ Request::METHOD_PUT ],
            'deleteAction404' => [ Request::METHOD_DELETE ],
        ];
    }


}
