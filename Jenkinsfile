pipeline {
    agent any
    environment { 
        TAG = "${env.BRANCH_NAME}"
        DOCKER_CREDS = credentials('hubtoken')
    }
    stages {
        stage('Code linting') {
            steps {
                script {
                    // Check bash script formatting
                    sh 'find * -name *.sh -print0 | xargs -n1 -I "{}" -0 docker run -i -v "$(pwd)":/workdir -w /workdir unibeautify/beautysh -c "/workdir/{}"'
                    // Lint bash scripts using shellcheck
                    sh 'find * -name *.sh -print0 | xargs -n1 -I "{}" -0 docker run -i --rm -v "$PWD":/src  koalaman/shellcheck "/src/{}"'
                    // Lint Dockerfiles using hadolint
                    sh 'find * -name Dockerfile* -print0 | xargs -n1 -I "{}" -0 docker run -i --rm -v "$PWD":/src hadolint/hadolint hadolint "/src/{}"'
                }
            }
        }
        stage('Build') {
            when { anyOf { branch 'master'; changeRequest(); } }
            parallel {
                stage('PHP 7.4') {
                   steps {
                        script {
                            withEnv(['VERSION=7.4']) {
                                sh 'docker build -t "drydockcloud/drupal-acquia-php-${VERSION}:${TAG}" ./php --build-arg version="${VERSION}"'
                            }
                        }
                    }
                }
                stage('PHP 8.0') {
                   steps {
                        script {
                            withEnv(['VERSION=8.0']) {
                                sh 'docker build -t "drydockcloud/drupal-acquia-php-${VERSION}:${TAG}" ./php --build-arg version="${VERSION}"'
                            }
                        }
                    }
                }
                stage('httpd') {
                    steps {
                        script {
                            sh 'docker build -t "drydockcloud/drupal-acquia-httpd:${TAG}" ./httpd'
                        }
                    }
                }
                stage('MySQL') {
                    steps {
                        script {
                            sh 'docker build -t "drydockcloud/drupal-acquia-mysql:${TAG}" ./mysql'
                        }
                    }
                }
            }
        }
        stage('Test') {
            when { anyOf { branch 'master'; changeRequest(); } }
            stages {
                stage('Test PHP 7.4') {
                    steps {
                        script {
                            withEnv(['VERSION=7.4']) {
                                sh 'test/test.sh'
                            }
                        }
                    }
                }
                stage('Test PHP 8.0') {
                    steps {
                        script {
                            withEnv(['VERSION=8.0']) {
                                sh 'test/test.sh'
                            }
                        }
                    }
                }
            }
        }
        stage('Push') {
            when { anyOf { branch 'master'; changeRequest(); } }
            stages {
                stage('Push PHP 7.4') {
                    steps {
                        script {
                            withEnv(['VERSION=7.4']) {
                                sh 'docker login --username civicactionsdrydock  --password $DOCKER_CREDS || true'
                                sh '''docker tag drydockcloud/drupal-acquia-php-7.4:${TAG} drydockcloud/drupal-acquia-php-7.4:latest
                                docker push drydockcloud/drupal-acquia-php-7.4:latest'''
                            }
                        }
                    }
                }
                stage('Push PHP 8.0') {
                    steps {
                        script {
                            withEnv(['VERSION=8.0']) {
                                sh 'docker login --username civicactionsdrydock  --password $DOCKER_CREDS || true'
                                sh '''docker tag drydockcloud/drupal-acquia-php-8.0:${TAG} drydockcloud/drupal-acquia-php-8.0:latest
                                docker push drydockcloud/drupal-acquia-php-8.0:latest'''
                            }
                        }
                    }
                }
            }
        }
    }
}

