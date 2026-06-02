import '../bootstrap';
import './styles.css';
import '@tabler/core/dist/css/tabler.min.css';
import '@tabler/core/dist/js/tabler.min.js';

import { createApp } from 'vue';
import AdminApp from './AdminApp.vue';

const app = document.getElementById('admin-app');

if (app) {
    createApp(AdminApp).mount(app);
}
