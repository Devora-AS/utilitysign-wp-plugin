module.exports = {
  testEnvironment: 'jsdom',
  setupFilesAfterEnv: ['<rootDir>/setup.js'],
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/../src/$1',
  },
  transform: {
    '^.+\\.(js|jsx|ts|tsx)$': ['babel-jest', {
      presets: [
        ['@babel/preset-env', {
          targets: {
            node: 'current'
          }
        }],
        ['@babel/preset-react', {
          runtime: 'automatic'
        }],
        ['@babel/preset-typescript', {
          isTSX: true,
          allExtensions: true
        }]
      ],
      plugins: [
        '@babel/plugin-proposal-class-properties'
      ]
    }],
  },
  moduleFileExtensions: ['js', 'jsx', 'ts', 'tsx', 'json'],
  testMatch: [
    '<rootDir>/../src/**/__tests__/**/*.{js,jsx,ts,tsx}',
    '<rootDir>/../src/**/*.{test,spec}.{js,jsx,ts,tsx}',
    '<rootDir>/unit/**/*.{test,spec}.{js,jsx,ts,tsx}',
  ],
  testPathIgnorePatterns: [
    '<rootDir>/e2e/',
    '<rootDir>/integration/',
    '<rootDir>/node_modules/',
  ],
  collectCoverageFrom: [
    '../src/**/*.(js|jsx|ts|tsx)',
    '!../src/**/*.d.ts',
    '!../src/**/*.stories.(js|jsx|ts|tsx)',
    '!../src/**/__tests__/**',
    '!../src/**/node_modules/**',
  ],
  coverageThreshold: {
    global: { branches: 70, functions: 70, lines: 70, statements: 70 },
  },
  coverageDirectory: 'coverage',
  coverageReporters: ['text', 'lcov', 'html'],
  moduleDirectories: ['node_modules', 'src'],
  testTimeout: 10000,
  clearMocks: true,
  restoreMocks: true,
  verbose: true,
  extensionsToTreatAsEsm: ['.ts', '.tsx'],
  transformIgnorePatterns: [
    'node_modules/(?!(.*\\.mjs$|@testing-library|@wordpress|@babel|@radix-ui))',
  ],
  testEnvironmentOptions: { url: 'http://localhost' },
  modulePaths: ['<rootDir>/../src'],
};


