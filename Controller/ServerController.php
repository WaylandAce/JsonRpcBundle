<?php /** @noinspection PhpUnusedAliasInspection */

namespace NeoFusion\JsonRpcBundle\Controller;

use NeoFusion\JsonRpcBundle\DependencyInjection\ServiceList;
use NeoFusion\JsonRpcBundle\Utils\JsonRpcBatchResponse;
use NeoFusion\JsonRpcBundle\Utils\JsonRpcError;
use NeoFusion\JsonRpcBundle\Utils\JsonRpcException;
use NeoFusion\JsonRpcBundle\Utils\JsonRpcInterface;
use NeoFusion\JsonRpcBundle\Utils\JsonRpcSingleRequest;
use NeoFusion\JsonRpcBundle\Utils\JsonRpcSingleResponse;
use phpDocumentor\Reflection\DocBlock\Tag;
use phpDocumentor\Reflection\DocBlock\Tags\Param;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use phpDocumentor\Reflection\DocBlockFactory;


class ServerController extends Controller
{
    /** @var DocBlockFactory */
    protected $factory;

    /**
     * @Route("/json_rpc")
     *
     * Entry point for handler of API requests
     *
     * @param Request $request
     *
     * @return Response|JsonResponse
     * @throws \ReflectionException
     */
    public function processAction(Request $request): Response
    {
        $content = $request->getContent();

        if ($request->request->has('smd')) {

            $answer = $this->processSmd();
        } else {
            $answer = $this->processContent($content);
        }

        return ($answer === null) ? new Response() : new JsonResponse($answer);
    }

    /**
     * @return array
     * @throws \ReflectionException
     */
    private function processSmd(): array
    {
        /** @var ServiceList $test */
        $test     = $this->get(ServiceList::class);
        $response = [
            'services'   => [],
            'deprecated' => []
        ];

        $this->factory = DocBlockFactory::createInstance();

        foreach ($test as $serviceName => $service) {

            $reflection = new \ReflectionClass($service);
            foreach ($this->getMethods($reflection) as $method) {
                $methodData = $this->scanMethod($method);
                if (is_null($methodData)) {
                    continue;
                }

                $response['services'][$serviceName][$method->getName()] = $methodData;
            }
        }

        return $response;
    }

    /**
     * @param \ReflectionClass $reflection
     * @return \Generator|\ReflectionMethod[]
     */
    public function getMethods(\ReflectionClass $reflection)
    {
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (in_array($method->getName(), ['__construct'])) {
                continue;
            }

            yield $method;
        }
    }

    /**
     * @param \ReflectionMethod $method
     * @return array|null
     * @throws \ReflectionException
     */
    public function scanMethod(\ReflectionMethod $method): ?array {

        $methodData = [];
        $params     = [];

        if ($method->getDocComment() !== false) {
            $docBlock = $this->factory->create($method->getDocComment());

            /** @var Tag|Param $tag */
            foreach ($docBlock->getTags() as $tag) {
                if ($tag->getName() == 'ignore') {
                    return null;
                }

                if ($tag->getName() == 'param') {
                    $params[$tag->getVariableName()] = $tag;
                }
            }

            $methodData['description'] = $docBlock->getSummary();
        }

        $methodData['params'] = [];

        $types = $this->getTypes($method);

        foreach ($method->getParameters() as $parameter) {
            if ($parameter->hasType() && ! $parameter->getType()->isBuiltin()) {
                continue;
            }

            $description = isset($params[$parameter->getName()]) ? (string)$params[$parameter->getName()]->getDescription() : null;

            $methodData['params'][$parameter->getName()] = [
                'description'  => $description,
                'typeHint'     => $types[$parameter->getName()] ?? null,
                'optional'     => $parameter->isDefaultValueAvailable(),
                'defaultValue' => $parameter->isOptional() ? var_export($parameter->getDefaultValue(), true) : '-'
            ];
        }

        return $methodData;
    }


    /**
     * Request body content processor
     *
     * @param string $content Request body content
     *
     * @return array|null
     */
    private function processContent(string $content): ?array
    {
        $jsonArray = json_decode($content, true);
        // Checking for JSON parsing errors
        if ($jsonArray === null) {
            $jsonRpcSingleResponse = new JsonRpcSingleResponse(null, new JsonRpcError(JsonRpcError::CODE_PARSE_ERROR));

            return $jsonRpcSingleResponse->toArray();
        }
        // Checking for array is not empty
        if (! (is_array($jsonArray) && ! empty($jsonArray))) {
            $jsonRpcSingleResponse = new JsonRpcSingleResponse(null, new JsonRpcError(JsonRpcError::CODE_INVALID_REQUEST));

            return $jsonRpcSingleResponse->toArray();
        }
        // Getting array type
        $isAssoc = $this->isAssocArray($jsonArray);
        // Making requests depending on array type
        if ($isAssoc) {
            $singleResult = $this->processSingleJsonArray($jsonArray);
            $answer       = ($singleResult instanceof JsonRpcInterface) ? $singleResult->toArray() : null;
        } else {
            $batchResult = new JsonRpcBatchResponse();
            foreach ($jsonArray as $singleJsonArray) {
                // Checking for array is not empty
                if (! (is_array($singleJsonArray) && ! empty($singleJsonArray))) {
                    $jsonRpcSingleResponse = new JsonRpcSingleResponse(null, new JsonRpcError(JsonRpcError::CODE_INVALID_REQUEST));
                    $batchResult->addResponse($jsonRpcSingleResponse);
                } else {
                    $singleResult = $this->processSingleJsonArray($singleJsonArray);
                    if ($singleResult instanceof JsonRpcInterface) {
                        $batchResult->addResponse($singleResult);
                    }
                }
            }
            $answer = $batchResult->isEmpty() ? null : $batchResult->toArray();
        }

        return $answer;
    }

    /**
     * Convert array to JsonRpcSingleRequest
     *
     * @param array $singleJsonArray
     *
     * @return JsonRpcSingleRequest
     */
    private function prepareSingleRequest(array $singleJsonArray): JsonRpcSingleRequest
    {
        return new JsonRpcSingleRequest(
            array_key_exists('jsonrpc', $singleJsonArray) ? $singleJsonArray['jsonrpc'] : null,
            array_key_exists('method', $singleJsonArray) ? $singleJsonArray['method'] : null,
            array_key_exists('params', $singleJsonArray) ? $singleJsonArray['params'] : null,
            array_key_exists('id', $singleJsonArray) ? $singleJsonArray['id'] : null
        );
    }

    /**
     * Processing array of JSON data
     *
     * @param array $singleJsonArray
     *
     * @return JsonRpcSingleResponse|null NULL, if JsonRpcSingleRequest is Notification
     */
    private function processSingleJsonArray(array $singleJsonArray): ?JsonRpcSingleResponse
    {
        $jsonRpcSingleRequest = $this->prepareSingleRequest($singleJsonArray);
        if ($jsonRpcSingleRequest->isValid()) {
            if ($jsonRpcSingleRequest->isNotification()) {
                return null;
            } else {
                return $this->processSingleRequest($jsonRpcSingleRequest);
            }
        } else {
            return new JsonRpcSingleResponse(null, new JsonRpcError(JsonRpcError::CODE_INVALID_REQUEST));
        }
    }

    /**
     * Making a single request
     *
     * @param JsonRpcSingleRequest $request
     *
     * @return JsonRpcSingleResponse
     */
    private function processSingleRequest(JsonRpcSingleRequest $request): JsonRpcSingleResponse
    {
        list($serviceName, $methodName) = explode('.', $request->getMethod());

        // Checking for service presence
        try {
            /** @var ServiceList $test */
            $test    = $this->get(ServiceList::class);
            $service = $test->getService($serviceName);
            if (! $service) {
                throw new ServiceNotFoundException('Service not found');
            }
        } catch (ServiceNotFoundException $e) {
            return new JsonRpcSingleResponse(null, new JsonRpcError(JsonRpcError::CODE_METHOD_NOT_FOUND), $request->getId());
        }

        // Checking for method presence
        if (! is_callable([$service, $methodName])) {
            return new JsonRpcSingleResponse(null, new JsonRpcError(JsonRpcError::CODE_METHOD_NOT_FOUND), $request->getId());
        }
        // Calling the method
        try {
            $reflection = new \ReflectionMethod(get_class($service), $methodName);
            $args       = $this->prepareArgs($reflection, $request->getParams());
            $result     = $reflection->invokeArgs($service, $args);

        } catch (\Exception $e) {
            // If error code exists in JsonRpcError list, than use it. Otherwise using standard CODE_SERVER_ERROR
            if (array_key_exists($e->getCode(), JsonRpcError::$errorMessages)) {
                $code = $e->getCode();
            } else {
                $code = JsonRpcError::CODE_SERVER_ERROR;
            }
            // If an exception has `getData` method and it doesn't return null, pass `data` as an array
//            if (is_callable(array($e, 'getData')) && $e->getData() !== null) {
            if ($e instanceof JsonRpcException) {
                $data = [
                    'code'    => $e->getCode(),
                    'message' => $e->getMessage(),
                    'data'    => $e->getData()
                ];
            } elseif (empty($e->getMessage())) {
                $data = null;
            } else {
                $data = $e->getMessage();
            }

            return new JsonRpcSingleResponse(null, new JsonRpcError($code, null, $data), $request->getId());
        }

        return new JsonRpcSingleResponse($result, null, $request->getId());
    }

    /**
     * Getting array type (associative / sequential)
     *
     * @param mixed $arr
     *
     * @return bool True, if associative
     */
    private function isAssocArray($arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * @param \ReflectionMethod $reflection
     * @param array $params
     * @return array
     * @throws \ReflectionException
     * @throws \Exception
     */
    private function prepareArgs(\ReflectionMethod $reflection, array $params): array
    {
        $types         = $this->getTypes($reflection);
        $isNamedParams = $this->isAssoc($params);
        $args          = [];

        /** @var \ReflectionParameter $parameter */
        foreach ($reflection->getParameters() as $k => $parameter) {
            $key = $isNamedParams ? $parameter->getName() : $k;

            if ($parameter->hasType() && ! $parameter->getType()->isBuiltin()) {
                throw new \Exception('Parameter "' . $parameter->getName() . '" is cant resolve');
            }

            if (! $parameter->isDefaultValueAvailable() && ! isset($params[$key])) {
                throw new \Exception('Parameter "' . $parameter->getName() . '" is mandatory');
            }

            if (isset($params[$key])) {
                $value = $params[$key];

                if (isset($types[$key])) {
                    switch ($types[$key]) {
                        case 'string':
                            if (! is_string($value)) {
                                throw new \Exception('Parameter "' . $parameter->getName() . '" must be string');
                            }
                            break;
                        case 'bool':
                            // cast to bool, if needed
                            if ($value === 1 || $value === 0 || $value === "1" || $value === "0") {
                                $value = (bool)($value);
                            }

                            if (! is_bool($value)) {
                                throw new \Exception('Parameter "' . $parameter->getName() . '" must be bool');
                            }
                            break;
                        case 'int':
                            if (! is_numeric($value)) {
                                throw new \Exception('Parameter "' . $parameter->getName() . '" must be numeric');
                            }
                            break;
                        case 'array':
                            if (! is_array($value)) {
                                throw new \Exception('Parameter "' . $parameter->getName() . '" must be array');
                            }
                            break;
                    }
                }

                $args[$parameter->getName()] = $value;
            } else {
                $args[$parameter->getName()] = $parameter->getDefaultValue();
            }

        }

        return $args;
    }

    /**
     * @param \ReflectionMethod $reflection
     * @return array
     */
    private function getTypes(\ReflectionMethod $reflection): array
    {
        $types = [];

        foreach ($reflection->getParameters() as $parameter) {
            if ($parameter->hasType()) {
                $types[$parameter->getName()] = strval($parameter->getType());
            }
        }

        return $types;
    }

    /**
     * @param array $arr
     * @return bool
     */
    private function isAssoc(array $arr): bool
    {
        if ([] === $arr) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }

}
