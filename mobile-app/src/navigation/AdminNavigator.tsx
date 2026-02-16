import { createNativeStackNavigator } from "@react-navigation/stack"
import { createBottomTabNavigator } from "@react-navigation/bottom-tabs"
import MaterialIcons from "react-native-vector-icons/MaterialIcons"

import AdminDashboard from "../screens/admin/AdminDashboard"
import Students from "../screens/admin/Students"
import Teachers from "../screens/admin/Teachers"
import Exams from "../screens/admin/Exams"
import Settings from "../screens/admin/Settings"
import ProfileScreen from "../screens/ProfileScreen"

const Stack = createNativeStackNavigator()
const Tab = createBottomTabNavigator()

function DashboardStack() {
  return (
    <Stack.Navigator screenOptions={{ headerShown: true }}>
      <Stack.Screen name="Dashboard" component={AdminDashboard} />
    </Stack.Navigator>
  )
}

export default function AdminNavigator() {
  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        tabBarIcon: ({ color, size }) => {
          let iconName

          if (route.name === "DashboardStack") {
            iconName = "dashboard"
          } else if (route.name === "Students") {
            iconName = "people"
          } else if (route.name === "Teachers") {
            iconName = "school"
          } else if (route.name === "Exams") {
            iconName = "assignment"
          } else if (route.name === "Settings") {
            iconName = "settings"
          } else if (route.name === "Profile") {
            iconName = "person"
          }

          return <MaterialIcons name={iconName} size={size} color={color} />
        },
        tabBarActiveTintColor: "#0066cc",
        tabBarInactiveTintColor: "#999",
        headerShown: true,
      })}
    >
      <Tab.Screen name="DashboardStack" component={DashboardStack} options={{ title: "Dashboard" }} />
      <Tab.Screen name="Students" component={Students} options={{ title: "Students" }} />
      <Tab.Screen name="Teachers" component={Teachers} options={{ title: "Teachers" }} />
      <Tab.Screen name="Exams" component={Exams} options={{ title: "Exams" }} />
      <Tab.Screen name="Settings" component={Settings} options={{ title: "Settings" }} />
      <Tab.Screen name="Profile" component={ProfileScreen} options={{ title: "Profile" }} />
    </Tab.Navigator>
  )
}
