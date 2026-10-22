import { createRequire } from 'node:module';

// eslint and @eslint/js are dev dependencies of the tests package, so
// they are resolved from tests/ (and from here up, for an install at the
// root).
const js = createRequire(new URL('./tests/', import.meta.url))('@eslint/js');

export default [
	{
		ignores: [
			'**/*.min.js',
			'**/jquery*.js',
			'js/jquery.flot.js',
			'**/flotr*/**',
			'**/bootstrap*',
			'**/codemirror*/**',
			'**/vendor/**',
			'**/node_modules/**',
		],
	},
	{
		files: ['js/**/*.js', 'plugins/**/*.js'],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'script',
		},
		rules: {
			...js.configs.recommended.rules,
			// ruTorrent relies on many implicit globals (jQuery, cross-file
			// functions, per-plugin objects), so no-undef would be almost
			// entirely false positives. Disable it rather than maintain an
			// exhaustive globals list; the rest of eslint:recommended stays on.
			'no-undef': 'off',
			// Empty catch blocks are used intentionally here (feature
			// detection, eval fallbacks); other empty blocks are still flagged.
			'no-empty': ['error', { allowEmptyCatch: true }],
			// Globals are cross-file here (utility helpers, per-plugin vars), so
			// only flag genuinely dead *local* variables; unused positional
			// callback params and catch bindings are left alone.
			'no-unused-vars': ['error', { vars: 'local', args: 'none', caughtErrors: 'none' }],
		},
	},
	{
		// Files that are ES modules (top-level import/export).
		files: ['js/backgroundtask.js', 'js/category-list.js', 'js/panel.js', 'plugins/rss/bbcode.js'],
		languageOptions: { sourceType: 'module' },
	},
	{
		// The jest specs run under node rather than in the page. Some are
		// CommonJS and some use import; babel-jest takes either, and
		// 'module' is the parser setting that accepts both. no-undef is off
		// for the reason it is off above: the globals here are node's and
		// jest's rather than the page's, and listing them exhaustively would
		// only go stale.
		files: ['tests/**/*.js'],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'module',
		},
		rules: {
			...js.configs.recommended.rules,
			'no-undef': 'off',
			'no-empty': ['error', { allowEmptyCatch: true }],
			'no-unused-vars': ['error', { vars: 'local', args: 'none', caughtErrors: 'none' }],
		},
	},
];
