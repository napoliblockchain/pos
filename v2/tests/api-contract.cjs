// Structural/repository checks only, not full OpenAPI validation or backend integration tests.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const file = path.resolve(__dirname, '../docs/pos-api.openapi.json');
const spec = JSON.parse(fs.readFileSync(file, 'utf8'));
assert.equal(spec.openapi, '3.1.0');
assert.equal(spec['x-contract-status'], 'partial-backend-login-implemented');
assert.equal(spec.paths['/sessions'].post['x-implementation-status'], 'backend-implemented');
assert.ok(spec.paths['/sessions'].post.responses['405']);
assert.ok(spec.paths['/sessions'].post.parameters.some(parameter => parameter.name === 'X-POS-Operator-IP'));
assert.deepEqual(Object.keys(spec.components.schemas.Operator.properties), ['display_name']);
assert.deepEqual(spec.security, [{ PosClient: [], OperatorSession: [] }]);
assert.deepEqual(spec.paths['/sessions'].post.security, [{ PosClient: [] }]);
const login = spec.components.schemas.Login;
assert.deepEqual(login.required, ['email', 'password']);
assert.equal(login.additionalProperties, false);
assert.equal(login.properties.password.writeOnly, true);
assert.equal(spec.components.schemas.OpenRequest.additionalProperties, false);
assert.deepEqual(spec.components.schemas.OpenRequest.required, ['instance_id']);
assert.deepEqual(spec.components.schemas.OpeningProof.required, ['instance_id', 'generation']);
assert.equal(spec.components.schemas.Opening.properties.generation.type, 'string');
assert.deepEqual(Object.keys(spec.components.schemas.Terminal.properties), ['id', 'store_name', 'name', 'availability']);
const methods = ['get', 'post', 'delete', 'put', 'patch'];
const ids = new Set();
let operations = 0; let references = 0;
function walk(value) {
  if (!value || typeof value !== 'object') return;
  if (value.$ref) {
    assert.ok(value.$ref.startsWith('#/'), 'Only local references permitted');
    const resolved = value.$ref.slice(2).split('/').reduce((result, key) => result?.[key.replace(/~1/g, '/').replace(/~0/g, '~')], spec);
    assert.ok(resolved, 'Unresolved reference: ' + value.$ref);
    ++references;
  }
  for (const child of Object.values(value)) walk(child);
}
walk(spec);
for (const [route, item] of Object.entries(spec.paths)) {
  assert.ok(!/pay|invoice|force-unlock|wallet/.test(route), 'No unapproved payment or manager transport endpoints');
  for (const [method, operation] of Object.entries(item)) {
    if (!methods.includes(method)) continue;
    assert.ok(operation.operationId && !ids.has(operation.operationId));
    ids.add(operation.operationId); ++operations;
    if (operation.operationId !== 'createOperatorSession') assert.equal(operation['x-implementation-status'], 'draft-not-implemented');
    assert.ok(operation.responses['401'] && operation.responses['503']);
    assert.notDeepEqual(operation.security, [], 'No anonymous operations');
    for (const match of route.matchAll(/\{([^}]+)\}/g)) {
      assert.ok(operation.parameters.some(parameter => parameter.name === match[1] && parameter.in === 'path' && parameter.required));
    }
  }
}
assert.equal(operations, 7);
assert.ok(spec.paths['/terminals/{terminal_id}/openings'].post.responses['409']);
assert.ok(spec.paths['/openings/{opening_id}/heartbeat'].post.responses['409']);
console.log(`PASS POS API structure: one implemented login, ${operations - 1} draft operations, ${references} local references resolved`);
