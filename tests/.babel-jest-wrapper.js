const path = require("path");
const { createTransformer } = require("babel-jest");
module.exports = createTransformer({
  presets: ["@babel/preset-env"],
  babelrcRoots: path.resolve(__dirname),
});
