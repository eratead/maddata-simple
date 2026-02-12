// v1

import './bootstrap';

import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);

window.Alpine = Alpine;

Alpine.start();

import './utils.js';
import { saveWithExpiry, getWithExpiry, po } from './utils';

window.saveWithExpiry = saveWithExpiry;
window.getWithExpiry = getWithExpiry;
window.po = po;
import './campaigns.js';
