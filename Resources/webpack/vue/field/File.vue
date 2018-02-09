<template>
    <div class="js-upload uk-placeholder uk-text-center">

        <div v-if="error" class="uk-alert-danger" uk-alert>
            <a class="uk-alert-close" uk-close></a>
            <p>{{ error }}</p>
        </div>

        <div v-if="fileName" class="uk-flex uk-flex-middle">
            <div class="uk-margin-small-right">
                <span uk-icon="icon: file; ratio: 2"></span>
            </div>
            <div class="uk-text-left uk-flex-auto">
                {{ fileName }}<br />
                <small>{{ fileSizeHuman }}</small>
            </div>
            <div>
                <button uk-close v-on:click.prevent="clearFile"></button>
            </div>
        </div>
        <div v-else>
            <span uk-icon="icon: cloud-upload"></span>
            <span class="uk-text-middle">Add file by dropping it here or</span>
            <div uk-form-custom>
                <input type="file">
                <span class="uk-link">selecting one</span>
            </div>
        </div>

        <div v-if="loading" class="uk-text-center" style="position: absolute; top: 0; right: 0; bottom: 0; left: 0; background: rgba(255,255,255,0.75);">
            <div style="position: absolute; top: 50%; margin-top: -15px;" uk-spinner></div>
        </div>

        <input type="hidden" :name="name + '[name]'" :value="fileName" />
        <input type="hidden" :name="name + '[type]'" :value="fileType" />
        <input type="hidden" :name="name + '[size]'" :value="fileSize" />
        <input type="hidden" :name="name + '[id]'" :value="fileId" />
    </div>
</template>

<script>

    import UIkit from 'uikit';

    export default {
        data() {
            var value = JSON.parse(this.value);

            return {
                fileName: value.name,
                fileType: value.type,
                fileSize: value.size,
                fileId: value.id,
                error: null,
                loading: false
            };
        },
        computed: {
            fileSizeHuman: function() {
                let size = (this.fileSize / 1024);

                if(size < 1000) {
                    return Math.floor(size) + 'Kb';
                }
                size = size / 1000;
                if(size < 1000) {
                    return Math.floor(size) + 'Mb';
                }
                size = size / 1000;
                return Math.floor(size) + 'Gb';
            }
        },

        mounted() {

            // Init upload element.
            let tmpFile = null;
            let tmpId = null;
            let t = this;

            let uploader = UIkit.upload(this.$el, {

                multiple: false,
                name: 'file',
                type: 'PUT',
                allow: '*.' + (this.fileTypes ? this.fileTypes : '*').split(',').join('|*.'),

                beforeAll: () => {
                    this.error = null;
                    this.loading = true;
                },
                completeAll: () => {
                    this.fileName = tmpFile.name;
                    this.fileSize = tmpFile.size;
                    this.fileId = tmpId;
                    tmpFile = null;
                    tmpId = null;
                    this.loading = false;
                },
                error: (error) => {
                    this.error = error;
                    tmpFile = null;
                    tmpId = null;
                    this.loading = false;
                },
                fail: (error) => {
                    this.error = error;
                    tmpFile = null;
                    tmpId = null;
                    this.loading = false;
                }
            });
            uploader.upload = function(files){
                if(files.length === 0) {
                    return;
                }

                if(t.fileName) {
                    t.error = 'To upload a new file, delete the current file first.';
                    console.log(this);
                    return;
                }

                tmpFile = files[0];

                function match(pattern, path) {
                    return path.match(new RegExp(`^${pattern.replace(/\//g, '\\/').replace(/\*\*/g, '(\\/[^\\/]+)*').replace(/\*/g, '[^\\/]+').replace(/((?!\\))\?/g, '$1.')}$`, 'i'));
                }

                if (this.allow && !match(this.allow, tmpFile.name)) {
                    this.fail(this.msgInvalidName.replace('%s', this.allow));
                    return;
                }

                let data = new FormData();
                data.append('filename', tmpFile.name);
                data.append('field', t.fieldPath);

                UIkit.util.ajax(t.uploadSignUrl, {
                    method: 'POST',
                    data: data
                }).then((result) => {
                    this.url = result.response;
                    let urlParts = this.url.split('/');
                    tmpId = urlParts[urlParts.length - 2];
                    UIkit.components.upload.options.methods.upload.call(this, [tmpFile]);
                }, () => {
                    t.error = 'Cannot sign file for uploading';
                });
            };
        },

        props: [
            'name',
            'value',
            'fileTypes',
            'fieldPath',
            'uploadSignUrl'
        ],
        methods: {
            clearFile: function(){
                this.error = null;
                UIkit.modal.confirm('Do you really want to delete the selected file?').then(() => {
                    this.fileName = null;
                    this.fileSize = null;
                    this.fileId = null;
                    this.fileType = null;
                }, () => {});
            }
        }
    };
</script>

<style lang="scss" scoped>
</style>