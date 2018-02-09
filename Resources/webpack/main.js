
import Vue from "vue";
import vueCustomElement from 'vue-custom-element';

import File from "./vue/field/file.vue";

// Use VueCustomElement
Vue.use(vueCustomElement);

Vue.customElement('united-cms-storage-file-field', File);