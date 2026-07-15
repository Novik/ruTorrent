import js from '@eslint/js';

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
			'tests/**',
			'node_modules/**',
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
];
