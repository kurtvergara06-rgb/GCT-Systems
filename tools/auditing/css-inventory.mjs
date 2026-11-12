// Read-only source audit. Findings are review candidates, never deletion advice.
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const repo = path.resolve(fileURLToPath(new URL('../../', import.meta.url)));
const require = createRequire(path.join(repo, 'package.json'));
const postcss = require('postcss'); // Already installed by the project's Vite toolchain.
const relative = file => path.relative(repo, file).replaceAll('\\', '/');
const walk = directory => fs.readdirSync(directory, { withFileTypes: true }).flatMap(entry => {
    const target = path.join(directory, entry.name);
    return entry.isDirectory() ? walk(target) : [target];
});
const sources = [...walk(path.join(repo, 'resources')), ...walk(path.join(repo, 'app')), ...walk(path.join(repo, 'routes')), path.join(repo, 'vite.config.js')]
    .filter(file => /\.(css|js|php)$/.test(file))
    .map(file => ({ file: relative(file), text: fs.readFileSync(file, 'utf8') }));
const sourceFiles = new Set(sources.map(source => source.file));
const importConsumers = new Map();
for (const source of sources) {
    for (const match of source.text.matchAll(/(?:\bfrom|\bimport|@import)\s*(?:url\()?\s*['"]([^'"]+)['"]/g)) {
        if (!match[1].startsWith('.')) continue;
        const target = path.posix.normalize(path.posix.join(path.posix.dirname(source.file), match[1]));
        if (!sourceFiles.has(target)) continue;
        const consumers = importConsumers.get(target) || [];
        consumers.push(source.file);
        importConsumers.set(target, consumers);
    }
}

const styles = [];
for (const source of sources.filter(item => item.file.endsWith('.css'))) {
    const ast = postcss.parse(source.text, { from: source.file });
    const groups = new Map();
    const imports = [], keyframes = [];
    let important = 0, hardColors = 0, rules = 0;
    ast.walkDecls(declaration => {
        important += Boolean(declaration.important);
        hardColors += /#(?:[a-f\d]{3,8})\b|rgba?\(/i.test(declaration.value);
    });
    ast.walkAtRules('import', rule => imports.push(rule.params));
    ast.walkAtRules(/keyframes$/, rule => keyframes.push(rule.params));
    ast.walkRules(rule => {
        rules++;
        const context = [];
        for (let parent = rule.parent; parent && parent.type !== 'root'; parent = parent.parent) {
            context.unshift(parent.type === 'atrule' ? `@${parent.name} ${parent.params}` : parent.selector);
        }
        const key = `${context.join(' > ')} | ${rule.selector}`;
        const rows = groups.get(key) || [];
        rows.push({
            line: rule.source.start.line,
            declarations: rule.nodes.filter(node => node.type === 'decl')
                .map(node => `${node.prop}:${node.value}${node.important ? ' !important' : ''}`),
        });
        groups.set(key, rows);
    });
    const duplicates = [...groups].filter(([, rows]) => rows.length > 1)
        .map(([selector, rows]) => ({ selector, rows }));
    const consumers = [...new Set([
        ...sources.filter(item => item.file !== source.file && item.text.includes(source.file)).map(item => item.file),
        ...(importConsumers.get(source.file) || []),
    ])];
    styles.push({ file: source.file, bytes: Buffer.byteLength(source.text), lines: source.text.split('\n').length, rules, important, hardColors, imports, keyframes, consumers, duplicates });
}
const templates = sources.filter(source => source.file.startsWith('resources/views/') && source.file.endsWith('.blade.php')).map(source => ({
    file: source.file,
    kind: /<x-layout\.app\b|@extends\s*\(|<!doctype\s+html|<html\b/i.test(source.text) ? 'page-layout-or-standalone-document' : 'partial-or-component',
    assets: [...new Set([...source.text.matchAll(/resources\/(?:css|js)\/[^'"\s]+\.(?:css|js)/g)].map(match => match[0]))],
    hasChart: /<canvas\b/.test(source.text),
    inlineStyleAttributes: (source.text.match(/\bstyle\s*=/g) || []).length,
    styleBlocks: (source.text.match(/<style[\s>]/g) || []).length,
}));
const result = {
    generated: new Date().toISOString(),
    scope: 'Static candidates only. Repeated selectors do not prove redundant behavior. Consumers include literal references and resolved relative imports; dynamic construction, inherited layout assets and framework/package imports still need review.',
    cssFiles: styles.length, templates: templates.length, styles, views: templates,
};
const output = process.argv[2] || path.join(repo, 'storage/logs/css-inventory.json');
fs.mkdirSync(path.dirname(output), { recursive: true });
fs.writeFileSync(output, JSON.stringify(result, null, 2));
console.log(JSON.stringify({ cssFiles: styles.length, templates: templates.length, standaloneTemplates: templates.filter(view => view.kind !== 'partial-or-component').length, sourceBytes: styles.reduce((sum, style) => sum + style.bytes, 0), importantDeclarations: styles.reduce((sum, style) => sum + style.important, 0), repeatedSelectorGroups: styles.reduce((sum, style) => sum + style.duplicates.length, 0) }, null, 2));