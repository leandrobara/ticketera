import '../bootstrap';
import './styles.css';

import { createApp } from 'vue';
import SiteApp from './SiteApp.vue';

const app = document.getElementById('site-app');

if (app) {
  createApp(SiteApp).mount(app);
}
