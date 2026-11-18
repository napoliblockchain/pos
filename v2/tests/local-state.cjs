// Host test helper: mirror the launcher's path-only env lookup, never evaluate .env.
const fs = require('node:fs');
const path = require('node:path');
const project = path.resolve(__dirname, '../../..');
function localState(name) {
  let value = process.env[name] || '';
  if (!value && fs.existsSync(path.join(project, '.env'))) {
    for (const line of fs.readFileSync(path.join(project, '.env'), 'utf8').split(/\r?\n/)) {
      if (line.startsWith(name + '=')) value = line.slice(name.length + 1).replace(/["']/g, '');
    }
  }
  if (!path.isAbsolute(value) || value.includes('\n') || fs.lstatSync(value).isSymbolicLink()) throw new Error('Set an absolute private ' + name);
  const resolved = fs.realpathSync(value);
  const stat = fs.statSync(resolved);
  if (!stat.isDirectory() || stat.uid !== process.getuid() || (stat.mode & 0o077) || resolved === project || resolved.startsWith(project + path.sep)) throw new Error('Unsafe private ' + name);
  return resolved;
}
module.exports = { localState };
