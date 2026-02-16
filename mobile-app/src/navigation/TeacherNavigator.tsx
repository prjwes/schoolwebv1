import { createNativeStackNavigator } from "@react-navigation/stack"
import { createBottomTabNavigator } from "@react-navigation/bottom-tabs"
import MaterialIcons from "react-native-vector-icons/MaterialIcons"

import TeacherDashboard from "../screens/teacher/TeacherDashboard"
import Timetable from "../screens/teacher/Timetable"
import Students from "../screens/teacher/Students"
import Exams from "../screens/teacher/Exams"
import ProfileScreen from "../screens/ProfileScreen"

const Stack = createNativeStackNavigator()
const Tab = createBottomTabNavigator()

function DashboardStack() {
  return (
    <Stack.Navigator screenOptions={{ headerShown: true }}>
      <Stack.Screen name="Dashboard" component={TeacherDashboard} />
    </Stack.Navigator>
  )
}

export default function TeacherNavigator() {
  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        tabBarIcon: ({ color, size }) => {
          let iconName

          if (route.name === "DashboardStack") {
            iconName = "dashboard"
          } else if (route.name === "Timetable") {
            iconName = "schedule"
          } else if (route.name === "Students") {
            iconName = "people"
          } else if (route.name === "Exams") {
            iconName = "assignment"
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
      <Tab.Screen name="Timetable" component={Timetable} options={{ title: "Timetable" }} />
      <Tab.Screen name="Students" component={Students} options={{ title: "Students" }} />
      <Tab.Screen name="Exams" component={Exams} options={{ title: "Exams" }} />
      <Tab.Screen name="Profile" component={ProfileScreen} options={{ title: "Profile" }} />
    </Tab.Navigator>
  )
}
