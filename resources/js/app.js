import './bootstrap';

import Alpine from 'alpinejs';
import webauthn from './webauthn';

window.Alpine = Alpine;
window.cn = window.cn || {};
window.cn.webauthn = webauthn;

Alpine.start();
