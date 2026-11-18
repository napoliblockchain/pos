// Host-only path checks: no Docker, credentials or network access.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { localState } = require('./local-state.cjs');
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'pos-state-test-'));
const previous = process.env.POS_LOCAL_STATE;
let checks = 0;
function rejects(value) {
  process.env.POS_LOCAL_STATE = value;
  assert.throws(() => localState('POS_LOCAL_STATE'));
  ++checks;
}
try {
  fs.chmodSync(dir, 0o700);
  process.env.POS_LOCAL_STATE = dir;
  assert.equal(localState('POS_LOCAL_STATE'), dir); ++checks;
  rejects('relative/path');
  rejects(dir + '\n');
  fs.chmodSync(dir, 0o755); rejects(dir); fs.chmodSync(dir, 0o700);
  fs.writeFileSync(path.join(dir, 'file'), '', { mode: 0o600 }); rejects(path.join(dir, 'file'));
  fs.symlinkSync(dir, path.join(dir, 'link')); rejects(path.join(dir, 'link'));
  rejects(path.resolve(__dirname, '../../..'));
  console.log('PASS ' + checks + ' private local path checks');
} finally {
  if (previous === undefined) delete process.env.POS_LOCAL_STATE;
  else process.env.POS_LOCAL_STATE = previous;
  fs.rmSync(dir, { recursive: true });
}
