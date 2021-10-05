import template from './swag-migration-profile-magento-local-credential-form.html.twig';

const { Component } = Shopware;

Component.register('swag-migration-profile-magento-local-credential-form', {
    template,

    props: {
        credentials: {
            type: Object,
            default() {
                return {};
            },
        },
    },

    data() {
        return {
            inputCredentials: {
                dbHost: '',
                dbPort: '3306',
                dbUser: '',
                dbPassword: '',
                dbName: '',
                installationRoot: '',
                shopUrl: '',
                tablePrefix: '',
            },
            shopUrlActive: false,
        };
    },

    watch: {
        credentials: {
            immediate: true,
            handler(newCredentials) {
                if (newCredentials === null || Object.keys(newCredentials).length < 1) {
                    this.emitCredentials(this.inputCredentials);
                    return;
                }

                this.inputCredentials = newCredentials;
                if (this.inputCredentials.shopUrl !== undefined
                    && this.inputCredentials.shopUrl !== 'http://'
                    && this.inputCredentials.shopUrl !== 'https://'
                    && this.inputCredentials.shopUrl !== ''
                ) {
                    this.shopUrlActive = true;
                }

                this.emitOnChildRouteReadyChanged(
                    this.areCredentialsValid(this.inputCredentials),
                );
            },
        },

        inputCredentials: {
            deep: true,
            handler(newInputCredentials) {
                this.emitCredentials(newInputCredentials);
            },
        },

        shopUrlActive(newValue) {
            if (newValue === true) {
                this.inputCredentials.installationRoot = '';
            } else {
                this.inputCredentials.shopUrl = '';
            }
            this.emitCredentials(this.inputCredentials);
        },
    },

    methods: {
        areCredentialsValid(newInputCredentials) {
            return (
                this.validateInput(newInputCredentials.dbHost) &&
                this.validateInput(newInputCredentials.dbPort) &&
                this.validateInput(newInputCredentials.dbName) &&
                this.validateInput(newInputCredentials.dbUser) &&
                ((this.shopUrlActive === false && this.validateInput(newInputCredentials.installationRoot)) ||
                (this.shopUrlActive === true && this.validateShopUrl(newInputCredentials.shopUrl)))
            );
        },

        validateInput(input) {
            return input !== undefined && input !== null && input !== '';
        },

        validateShopUrl(input) {
            return input !== undefined && this.validateInput(input) && input !== 'http://' && input !== 'https://';
        },

        emitOnChildRouteReadyChanged(isReady) {
            this.$emit('onChildRouteReadyChanged', isReady);
        },

        emitCredentials(newInputCredentials) {
            this.$emit('onCredentialsChanged', newInputCredentials);
            this.emitOnChildRouteReadyChanged(
                this.areCredentialsValid(newInputCredentials),
            );
        },

        onKeyPressEnter() {
            this.$emit('onTriggerPrimaryClick');
        },
    },
});
