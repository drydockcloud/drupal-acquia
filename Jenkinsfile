pipeline {
    agent any
    environment { 
        TAG = "${env.BRANCH_NAME}"
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
                stage('PHP 7.2') {
                    steps {
                        script {
                            withEnv(['VERSION=7.2']) {
                                sh 'docker build -t "drydockcloud/drupal-acquia-php-${VERSION}:${TAG}" ./php --build-arg version="${VERSION}"'
                            }
                        }
                    }
                }
                stage('PHP 7.3') {
                    steps {
                        script {
                            withEnv(['VERSION=7.3']) {
                                sh 'docker build -t "drydockcloud/drupal-acquia-php-${VERSION}:${TAG}" ./php --build-arg version="${VERSION}"'
                            }
                        }
                    }
                }
                stage('PHP 7.4') {
                    steps {
                        script {
                            withEnv(['VERSION=7.4']) {
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
                stage('Test PHP 7.2') {
                    steps {
                        script {
                            withEnv(['VERSION=7.2']) {
                                sh 'test/test.sh'
                            }
                        }
                    }
                }
                stage('Test PHP 7.3') {
                    steps {
                        script {
                            withEnv(['VERSION=7.3']) {
                                sh 'test/test.sh'
                            }
                        }
                    }
                }
                stage('Test PHP 7.4') {
                    steps {
                        script {
                            withEnv(['VERSION=7.4']) {
                                sh 'test/test.sh'
                            }
                        }
                    }
                }
            }
        }
    }
}
