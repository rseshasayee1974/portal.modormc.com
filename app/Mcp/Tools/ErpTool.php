<?php
/*
Author: ragul-onemodo
Created: 2026-10-13 10:54:58 Asia/Calcutta (UTC+05:30)
*/

namespace App\Mcp\Tools;

use App\Mcp\{ErpToolCatalog, ErpToolGateway};
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\{Request, Response};
use Laravel\Mcp\Server\Tool;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ErpTool extends Tool
{
    public function __construct(string $name)
    {
        $this->name = $name;
        $this->description = ErpToolCatalog::definitions()[$name][2];
    }

    public function shouldRegister(): bool { return ErpToolCatalog::permits($this->name); }

    public function annotations(): array
    {
        return ['readOnlyHint'=>ErpToolCatalog::definitions()[$this->name][3], 'destructiveHint'=>false,
            'idempotentHint'=>$this->name === 'complete_follow_up' || ErpToolCatalog::definitions()[$this->name][3], 'openWorldHint'=>false];
    }

    public function schema(JsonSchema $schema): array
    {
        $fields = [];
        foreach (ErpToolCatalog::fields($this->name) as $name => $field) {
            $property = $schema->{$field[0]}();
            if (isset($field[2])) $property->enum($field[2]);
            if ($field[1]) $property->required();
            if (str_ends_with($name, '_date')) $property->description('Date in YYYY-MM-DD format.');
            if ($name === 'due_at') $property->description('Datetime in YYYY-MM-DD HH:mm:ss, selected entity timezone.');
            if ($name === 'per_page') $property->description('Number of records, 1 to 100; default 20.');
            $fields[$name] = $property;
        }
        return $fields;
    }

    public function handle(Request $request, ErpToolGateway $gateway): Response
    {
        try {
            return Response::json($gateway->execute($this->name, $request->all()));
        } catch (ValidationException $e) {
            return Response::error(json_encode(['code'=>422,'errors'=>$e->errors()], JSON_THROW_ON_ERROR));
        } catch (ModelNotFoundException $e) {
            return Response::error('404: Record not found in your accessible plant and records.');
        } catch (HttpExceptionInterface $e) {
            return Response::error($e->getStatusCode().': '.($e->getStatusCode() === 403 ? 'Required user permission is missing.' : 'The requested operation is not allowed.'));
        } catch (\Throwable $e) {
            report($e);
            return Response::error('Unable to complete this operation. Contact your administrator.');
        }
    }
}
