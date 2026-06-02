import '../bootstrap';

import { createApp } from 'vue';
import DashboardApp from './DashboardApp.vue';

const app = document.getElementById('dashboard-app');

if (app) {
    createApp(DashboardApp).mount(app);
}
