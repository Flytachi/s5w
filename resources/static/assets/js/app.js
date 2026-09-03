/* s5w admin — точка входа.
   Каркас (ui/*) поднимается на любой странице, разделы (features/*) вешают
   свои обработчики делегированно и включаются там, где есть их разметка. */

import * as theme from "./ui/theme.js";
import * as sidebar from "./ui/sidebar.js";
import * as popover from "./ui/popover.js";
import * as select from "./ui/select.js";
import { enhanceNumbers } from "./ui/number.js";
import * as modal from "./ui/modal.js";
import * as forms from "./ui/forms.js";
import * as actions from "./ui/actions.js";
import * as copy from "./ui/copy.js";
import * as charts from "./ui/charts.js";

import * as timezone from "./features/timezone.js";
import * as login from "./features/login.js";
import * as buckets from "./features/buckets.js";
import * as folders from "./features/folders.js";
import * as files from "./features/files.js";
import * as tokens from "./features/tokens.js";
import * as links from "./features/links.js";
import * as cachePolicy from "./features/cache-policy.js";
import * as upload from "./features/upload.js";

function boot() {
  timezone.init();
  theme.init();
  sidebar.init();
  popover.init();
  select.init();
  select.enhanceSelects();
  enhanceNumbers();
  modal.init();
  forms.init();
  actions.init();
  copy.init();
  charts.init();

  login.init();
  buckets.init();
  folders.init();
  files.init();
  tokens.init();
  links.init();
  cachePolicy.init();
  upload.init();

  document.documentElement.dataset.ready = "";
}

if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot);
else boot();
