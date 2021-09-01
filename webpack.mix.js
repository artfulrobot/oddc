let mix = require('laravel-mix');

mix
  .js('js-src/revenuedashboard.js', 'js/')
  .vue({version: 2})
 // .sass('src/app.scss', 'dist/')
;
