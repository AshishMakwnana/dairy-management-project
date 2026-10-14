import { StatusBar } from 'expo-status-bar';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';

import { checkApiHealth } from './src/services/api/health';

type ApiState = 'checking' | 'connected' | 'unavailable';

export default function App() {
  const [apiState, setApiState] = useState<ApiState>('checking');

  const checkConnection = async () => {
    setApiState('checking');

    try {
      await checkApiHealth();
      setApiState('connected');
    } catch {
      setApiState('unavailable');
    }
  };

  useEffect(() => {
    void checkConnection();
  }, []);

  return (
    <View style={styles.container}>
      <Text style={styles.eyebrow}>PHASE 0 FOUNDATION</Text>
      <Text style={styles.title}>DUDHO</Text>
      <Text style={styles.subtitle}>Smart Milk Management</Text>

      <View style={styles.statusCard}>
        {apiState === 'checking' ? (
          <ActivityIndicator color="#1565C0" />
        ) : (
          <View
            style={[
              styles.statusDot,
              apiState === 'connected' ? styles.connected : styles.unavailable,
            ]}
          />
        )}
        <View style={styles.statusCopy}>
          <Text style={styles.statusTitle}>
            {apiState === 'checking'
              ? 'Checking Laravel API'
              : apiState === 'connected'
                ? 'Laravel API connected'
                : 'Laravel API unavailable'}
          </Text>
          <Text style={styles.statusText}>
            {apiState === 'unavailable'
              ? 'Start the Docker services, then try again.'
              : 'Mobile runs outside Docker; the API runs inside Docker.'}
          </Text>
        </View>
      </View>

      {apiState === 'unavailable' && (
        <Pressable style={styles.button} onPress={() => void checkConnection()}>
          <Text style={styles.buttonLabel}>Retry connection</Text>
        </Pressable>
      )}

      <StatusBar style="dark" />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#F8FAFC',
    justifyContent: 'center',
    padding: 24,
  },
  eyebrow: {
    color: '#1565C0',
    fontSize: 12,
    fontWeight: '700',
    letterSpacing: 1.4,
    marginBottom: 8,
  },
  title: {
    color: '#172033',
    fontSize: 40,
    fontWeight: '800',
  },
  subtitle: {
    color: '#667085',
    fontSize: 18,
    marginBottom: 32,
  },
  statusCard: {
    alignItems: 'center',
    backgroundColor: '#FFFFFF',
    borderColor: '#D0D5DD',
    borderRadius: 16,
    borderWidth: 1,
    flexDirection: 'row',
    minHeight: 112,
    padding: 20,
  },
  statusDot: {
    borderRadius: 8,
    height: 16,
    width: 16,
  },
  connected: {
    backgroundColor: '#2E7D32',
  },
  unavailable: {
    backgroundColor: '#D32F2F',
  },
  statusCopy: {
    flex: 1,
    marginLeft: 16,
  },
  statusTitle: {
    color: '#172033',
    fontSize: 17,
    fontWeight: '700',
  },
  statusText: {
    color: '#667085',
    fontSize: 14,
    lineHeight: 20,
    marginTop: 4,
  },
  button: {
    alignItems: 'center',
    backgroundColor: '#1565C0',
    borderRadius: 12,
    marginTop: 16,
    minHeight: 50,
    justifyContent: 'center',
    paddingHorizontal: 20,
  },
  buttonLabel: {
    color: '#FFFFFF',
    fontSize: 16,
    fontWeight: '700',
  },
});
